<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\BalanceApi;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Balance\Balance;
use SarissaOps\Enviatodo\Model\Order\Guide;
use SarissaOps\Enviatodo\Model\Order\OrderCreated;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Exception\EnviatodoOrderException;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * Buys Enviatodo labels in two phases: a draft holds the checkout quote uuid,
 * completion spends it after payment. The balance guard runs before any
 * mutating call because create_order mints a real, billable label.
 */
final class EnviatodoOrderManager implements EnviatodoOrderManagerInterface
{
    public function __construct(
        private readonly OrderApi $orderApi,
        private readonly BalanceApi $balanceApi,
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
        if ('' === $quoteUuid) {
            throw new \InvalidArgumentException('Enviatodo drafts require a quote uuid.');
        }

        $draft = new EnviatodoShipment($shipment, $quoteUuid, $mapping['provider_id'], $mapping['provider_service_id']);
        $manager = $this->registry->getManager();
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
        $guides = $created->getGuides();
        $first = $guides[0] ?? null;
        if (!$first instanceof Guide) {
            throw new EnviatodoOrderException('Enviatodo answered without guides; the label was not purchased.');
        }

        $draft->complete($first->getTrackingId(), (int) round($created->getSummary()->getTotal() * 100));
        $this->registry->getManager()->flush();

        $this->logger->info('Enviatodo label purchased.', ['tracking_id' => $first->getTrackingId()]);
    }
}
