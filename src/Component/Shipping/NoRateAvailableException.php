<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * No live rate exists for the requested provider pair and destination:
 * the zip-code catalog has no settlement, or the quote carries no matching
 * available row. Sylius hides methods whose calculator throws, so throwing
 * beats returning a misleading zero.
 */
final class NoRateAvailableException extends \RuntimeException
{
}
