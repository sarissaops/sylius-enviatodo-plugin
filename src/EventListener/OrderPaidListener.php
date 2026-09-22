<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\EventListener;

use Psr\Log\LoggerInterface;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoOrderManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Completes draft label rows when order payment transitions to paid (event
 * workflow.sylius_order_payment.completed.pay, the same seam core inventory
 * and state resolution use). Shipments without a draft row are skipped — the
 * uuid is stored at checkout (Step 3). Failures are logged, never thrown: the
 * order is already paid, so purchase retries happen from the admin (Step 3).
 */
final class OrderPaidListener
{
    public function __construct(
        private readonly EnviatodoOrderManagerInterface $orderManager,
        private readonly ProviderMap $providerMap,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
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
            $code = $shipment->getMethod()?->getCode();
            if (null === $code || !$this->providerMap->isBackedByEnviatodo($code)) {
                continue;
            }

            $draft = $this->repository->findOneByShipment($shipment);
            if (null === $draft || $draft->isCompleted()) {
                $this->logger->debug('Enviatodo label skipped: no completable draft row.');

                continue;
            }

            try {
                $this->orderManager->completeDraft($draft);
            } catch (\RuntimeException $exception) {
                $this->logger->error('Enviatodo label purchase failed.', ['error' => $exception->getMessage()]);
            }
        }
    }
}
