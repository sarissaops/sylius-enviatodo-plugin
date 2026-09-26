<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface as CoreShipmentInterface;
use Sylius\Component\Shipping\Checker\Eligibility\ShippingMethodEligibilityCheckerInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;

/**
 * Hides Enviatodo methods that cannot produce a live rate instead of letting
 * the calculator throw on the checkout page: unmapped codes abstain, but a
 * mapped method needs a complete origin/package configuration plus a
 * destination postcode the zip catalog resolves (Mexico-only operation).
 * Transport failures hide rather than 500; the calculator stays loud.
 */
final class EnviatodoMethodEligibilityChecker implements ShippingMethodEligibilityCheckerInterface
{
    public function __construct(
        private readonly ProviderMap $providerMap,
        private readonly ZipCodeApi $zipCodeApi,
    ) {
    }

    public function isEligible(ShippingSubjectInterface $shippingSubject, ShippingMethodInterface $shippingMethod): bool
    {
        $code = $shippingMethod->getCode();
        if (null === $code || null === $this->providerMap->getProviderService($code)) {
            return true;
        }

        if (!$this->configurationComplete($shippingMethod)) {
            return false;
        }

        $postcode = $this->destinationPostcode($shippingSubject);
        if ('' === $postcode) {
            return false;
        }

        try {
            $settlement = $this->zipCodeApi->show($postcode);
        } catch (\Exception) {
            return false;
        }

        return $settlement instanceof ZipCode && [] !== $settlement->getItems();
    }

    /**
     * Deterministic unquotability for the recalculation guard: misconfigured
     * methods and postcodes the catalog resolved-but-empty. Transport
     * failures are transient by definition — the guard preserves the
     * assigned method instead of stripping carts during an outage.
     */
    public function isDeterministicallyIneligible(ShippingSubjectInterface $shippingSubject, ShippingMethodInterface $shippingMethod): bool
    {
        $code = $shippingMethod->getCode();
        if (null === $code || null === $this->providerMap->getProviderService($code)) {
            return false;
        }

        if (!$this->configurationComplete($shippingMethod)) {
            return true;
        }

        $postcode = $this->destinationPostcode($shippingSubject);
        if ('' === $postcode) {
            return true;
        }

        try {
            $settlement = $this->zipCodeApi->show($postcode);
        } catch (\Exception) {
            return false;
        }

        return !$settlement instanceof ZipCode || [] === $settlement->getItems();
    }

    private function configurationComplete(ShippingMethodInterface $shippingMethod): bool
    {
        $configuration = $shippingMethod->getConfiguration();
        $quote = $configuration['quote'] ?? null;
        if (!\is_array($quote)) {
            return false;
        }

        return $this->quoteComplete($quote);
    }

    /**
     * @param array<mixed> $quote
     */
    private function quoteComplete(array $quote): bool
    {
        $origin = $quote['origin'] ?? null;
        $package = $quote['package'] ?? null;
        if (!\is_array($origin) || !\is_array($package)) {
            return false;
        }

        return [] === QuotePayload::missingKeys($origin, QuotePayload::REQUIRED_ORIGIN_KEYS) &&
            [] === QuotePayload::missingKeys($package, QuotePayload::REQUIRED_PACKAGE_KEYS);
    }

    private function destinationPostcode(ShippingSubjectInterface $shippingSubject): string
    {
        if (!$shippingSubject instanceof CoreShipmentInterface) {
            return '';
        }
        $order = $shippingSubject->getOrder();
        if (!$order instanceof CoreOrderInterface) {
            return '';
        }

        return trim((string) $order->getShippingAddress()?->getPostcode());
    }
}
