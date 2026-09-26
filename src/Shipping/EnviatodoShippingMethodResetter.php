<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;

/**
 * Recalculation guard: Sylius re-prices the assigned method without
 * re-checking eligibility, so a destination that turned unquotable (address
 * edit, stale quote, outage) would 500 the totals pass. This processor runs
 * between shipment creation and charge calculation and clears assigned
 * Enviatodo methods that lost eligibility — the shopper reselects instead
 * of meeting an error page. Non-Enviatodo methods are never touched.
 */
final class EnviatodoShippingMethodResetter implements OrderProcessorInterface
{
    public function __construct(private readonly EnviatodoMethodEligibilityChecker $eligibilityChecker)
    {
    }

    public function process(OrderInterface $order): void
    {
        if (!$order instanceof CoreOrderInterface) {
            return;
        }
        foreach ($order->getShipments() as $shipment) {
            $method = $shipment->getMethod();
            if (!$method instanceof ShippingMethodInterface) {
                continue;
            }

            try {
                $clear = $this->eligibilityChecker->isDeterministicallyIneligible($shipment, $method);
            } catch (\Exception) {
                $clear = false;
            }
            // Unmapped codes abstain as eligible inside the checker, so only
            // our own deterministically unquotable methods ever reach reset.
            // Transient provider failures preserve the assignment.
            if ($clear) {
                $shipment->setMethod(null);
            }
        }
    }
}
