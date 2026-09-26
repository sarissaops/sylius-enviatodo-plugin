<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\BalanceApi;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Balance\Balance;
use SarissaOps\Enviatodo\Model\Order\Guide;
use SarissaOps\Enviatodo\Model\Order\OrderCreated;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ShipmentContact;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Exception\EnviatodoOrderException;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * Buys Enviatodo labels at payment time: takes a fresh quote for the paid
 * shipment (never a stale checkout uuid), upserts the draft row, guards the
 * balance against the quoted total, and spends exactly one label per
 * shipment — duplicate payment events find a completed row and stop.
 * Flushes are unit-of-work wide (ORM 3 removed entity-scoped flush); the
 * pay transition flushes the same unit of work regardless.
 */
final class EnviatodoOrderManager implements EnviatodoOrderManagerInterface
{
    public function __construct(
        private readonly OrderApi $orderApi,
        private readonly BalanceApi $balanceApi,
        private readonly EnviatodoCalculator $calculator,
        private readonly ProviderMap $providerMap,
        private readonly ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly bool $insurance,
    ) {
    }

    public function createDraft(ShipmentInterface $shipment, string $quoteUuid, string $methodCode): EnviatodoShipment
    {
        $mapping = $this->providerMap->getProviderService($methodCode);
        if (null === $mapping) {
            throw new \LogicException('Enviatodo drafts require shipping methods inside the provider map.');
        }
        if ('' === trim($quoteUuid)) {
            throw new \InvalidArgumentException('Enviatodo drafts require a quote uuid.');
        }

        $manager = $this->entityManager();
        $existing = $manager->getRepository(EnviatodoShipment::class)->findOneBy(['shipment' => $shipment]);
        if (null !== $existing) {
            $existing->refreshQuote($quoteUuid, $mapping['provider_id'], $mapping['provider_service_id']);
            $manager->flush();

            return $existing;
        }

        $draft = new EnviatodoShipment($shipment, $quoteUuid, $mapping['provider_id'], $mapping['provider_service_id']);
        $manager->persist($draft);
        $manager->flush();

        return $draft;
    }

    public function completeDraft(EnviatodoShipment $draft): void
    {
        $balance = $this->balanceApi->show();
        if (!$balance instanceof Balance) {
            throw new \RuntimeException('BalanceApi must hydrate Balance models for the pre-purchase guard.');
        }
        if ($balance->getBalance() <= 0.0) {
            throw new EnviatodoOrderException('Enviatodo balance is empty; the label was not purchased.');
        }

        $created = $this->orderApi->create(
            $draft->getQuoteUuid(),
            $draft->getProviderId(),
            $draft->getProviderServiceId(),
            $this->insurance,
        );
        if (!$created instanceof OrderCreated) {
            throw new \RuntimeException('OrderApi must hydrate OrderCreated models for label purchase.');
        }
        $guide = $this->firstGuide($created);
        $totalMinor = (int) round($created->getSummary()->getTotal() * 100);

        $draft->complete($guide->getTrackingId(), $totalMinor);
        $draft->setGuideId($guide->getId());
        $this->entityManager()->flush();

        $this->logger->info('Enviatodo label purchased.', ['tracking_id' => $guide->getTrackingId()]);
    }

    /**
     * @param array<mixed> $quoteConfig method configuration: full `origin` address + `package`
     */
    public function purchaseForPaidShipment(ShipmentInterface $shipment, array $quoteConfig): void
    {
        $code = $shipment->getMethod()?->getCode();
        $mapping = null !== $code ? $this->providerMap->getProviderService($code) : null;
        if (null === $mapping) {
            throw new \LogicException('Enviatodo purchase requires shipping methods inside the provider map.');
        }
        $quote = QuotePayload::normalize($quoteConfig);
        $contact = ShipmentContact::fromShipment($shipment, ShipmentContact::fallbackTelephone($quote));

        $manager = $this->entityManager();

        // One transaction around find, lock, quote, guard, and buy: the row
        // lock is only legal inside a transaction, and holding it across the
        // billable create_order is what makes duplicate payment events safe.
        // Pay transitions are rare; a per-row lock for seconds is the price
        // of exactly-once label purchase without an idempotency key.
        $manager->wrapInTransaction(function () use ($manager, $shipment, $mapping, $quote, $contact): void {
            $draft = $manager->getRepository(EnviatodoShipment::class)->findOneBy(['shipment' => $shipment]);
            if (null !== $draft) {
                $manager->lock($draft, LockMode::PESSIMISTIC_WRITE);
                if ($draft->isCompleted()) {
                    return;
                }
            }

            $quoted = $this->calculator->quoteForAddress($quote, $contact, $mapping['provider_id'], $mapping['provider_service_id']);
            $uuid = trim($quoted->getTransactionUuid());
            if ('' === $uuid) {
                throw new EnviatodoOrderException('Enviatodo answered without a quote uuid; the label was not purchased.');
            }
            $expectedMinor = null;
            foreach ($quoted->getRates() as $rate) {
                if ($rate->getProviderId() === $mapping['provider_id'] && $rate->getProviderServiceId() === $mapping['provider_service_id']) {
                    $expectedMinor = $rate->getAmountMinor();

                    break;
                }
            }
            if (null === $expectedMinor) {
                throw new EnviatodoOrderException('Enviatodo has no rate for the paid shipment; the label was not purchased.');
            }

            $balance = $this->balanceApi->show();
            if (!$balance instanceof Balance) {
                throw new \RuntimeException('BalanceApi must hydrate Balance models for the pre-purchase guard.');
            }
            if ($balance->getBalance() < $expectedMinor / 100) {
                throw new EnviatodoOrderException('Enviatodo balance cannot cover the label; the label was not purchased.');
            }

            if (null === $draft) {
                $draft = new EnviatodoShipment($shipment, $uuid, $mapping['provider_id'], $mapping['provider_service_id']);
                $manager->persist($draft);
            } else {
                $draft->refreshQuote($uuid, $mapping['provider_id'], $mapping['provider_service_id']);
            }

            $created = $this->orderApi->create($uuid, $mapping['provider_id'], $mapping['provider_service_id'], $this->insurance);
            if (!$created instanceof OrderCreated) {
                throw new \RuntimeException('OrderApi must hydrate OrderCreated models for label purchase.');
            }
            $guide = $this->firstGuide($created);

            $draft->complete($guide->getTrackingId(), (int) round($created->getSummary()->getTotal() * 100));
            $draft->setGuideId($guide->getId());
            $manager->flush();

            $this->logger->info('Enviatodo label purchased.', ['tracking_id' => $guide->getTrackingId()]);
        });
    }

    private function firstGuide(OrderCreated $created): Guide
    {
        $guides = $created->getGuides();
        $first = $guides[0] ?? null;
        if (!$first instanceof Guide || '' === trim($first->getTrackingId()) || '' === trim($first->getId())) {
            throw new EnviatodoOrderException('Enviatodo answered without guides; the label was not purchased.');
        }

        return $first;
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = $this->registry->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            throw new \RuntimeException('Enviatodo label purchase requires Doctrine ORM.');
        }

        return $manager;
    }
}
