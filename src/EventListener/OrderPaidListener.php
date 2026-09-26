<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\EventListener;

use Psr\Log\LoggerInterface;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoOrderManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Buys Enviatodo labels when order payment transitions to paid (event
 * workflow.sylius_order_payment.completed.pay, the same seam core inventory
 * and state resolution use). The quote is taken fresh at payment time, so no
 * checkout-held uuid can go stale. Failures are logged, never thrown: the
 * order is already paid, so purchase retries happen from the admin.
 */
final class OrderPaidListener
{
    public function __construct(
        private readonly EnviatodoOrderManagerInterface $orderManager,
        private readonly ProviderMap $providerMap,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof OrderInterface) {
            return;
        }

        foreach ($subject->getShipments() as $shipment) {
            $method = $shipment->getMethod();
            $code = $method?->getCode();
            if (null === $code || !$this->providerMap->isBackedByEnviatodo($code)) {
                continue;
            }

            $configuration = $method->getConfiguration();
            $quote = $configuration['quote'] ?? null;
            if (!\is_array($quote)) {
                $this->logger->error('Enviatodo label skipped: method has no quote configuration.', ['method' => $code]);

                continue;
            }

            try {
                $this->orderManager->purchaseForPaidShipment($shipment, $quote);
            } catch (\Throwable $exception) {
                $this->logger->error('Enviatodo label purchase failed.', ['error' => $exception->getMessage()]);
            }
        }
    }
}
