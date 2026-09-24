<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\NoRateAvailableException;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface as CoreShipmentInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * Sylius calculator adapter. Quotes the order's shipping address live: the
 * per-method $configuration carries the warehouse `origin` (full address),
 * the `package`, and an optional `contact.telephone` shop fallback (checkout
 * collects no phone) under the `quote` key; the method code resolves to a
 * provider pair through the ProviderMap. Throws NoRateAvailableException when
 * no live rate matches — Sylius hides such methods instead of showing a
 * misleading free rate.
 */
final class EnviatodoShippingCalculator implements CalculatorInterface
{
    public const TYPE = 'enviatodo';

    public function __construct(
        private readonly EnviatodoCalculator $calculator,
        private readonly ProviderMap $providerMap,
    ) {
    }

    public function calculate(ShipmentInterface $subject, array $configuration): int
    {
        $code = $subject->getMethod()?->getCode();
        $mapping = null !== $code ? $this->providerMap->getProviderService($code) : null;
        if (null === $mapping) {
            throw new \LogicException('Enviatodo calculator cannot price shipping methods outside the provider map.');
        }

        $quote = $configuration['quote'] ?? null;
        if (!\is_array($quote)) {
            throw new \InvalidArgumentException('Enviatodo calculator configuration requires a "quote" payload array.');
        }
        /** @var array<string, mixed> $quoteConfig */
        $quoteConfig = $quote;

        try {
            $rate = $this->calculator->rateForAddress(
                $quoteConfig,
                $this->contact($subject, $quoteConfig),
                $mapping['provider_id'],
                $mapping['provider_service_id'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw new NoRateAvailableException(
                'Enviatodo cannot quote this destination: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }
        if (null === $rate) {
            throw new NoRateAvailableException(sprintf(
                'No Enviatodo rate for provider "%s" service "%s".',
                $mapping['provider_id'],
                $mapping['provider_service_id'],
            ));
        }

        return $rate->getAmountMinor();
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @param array<string, mixed> $quoteConfig
     *
     * @return array{fullName: string, email: string, telephone: string, street: string, postcode: string, countryCode: string}
     */
    private function contact(ShipmentInterface $subject, array $quoteConfig): array
    {
        if (!$subject instanceof CoreShipmentInterface) {
            throw new NoRateAvailableException('Enviatodo calculator requires a core shipment carrying the order.');
        }
        $order = $subject->getOrder();
        if (!$order instanceof CoreOrderInterface) {
            throw new NoRateAvailableException('Enviatodo calculator requires a shipment attached to an order.');
        }
        $address = $order->getShippingAddress();
        if (null === $address) {
            throw new NoRateAvailableException('Enviatodo calculator requires an order shipping address.');
        }

        $contact = $quoteConfig['contact'] ?? null;
        $fallbackTelephone = '';
        if (\is_array($contact) && isset($contact['telephone']) && \is_scalar($contact['telephone'])) {
            $fallbackTelephone = trim((string) $contact['telephone']);
        }

        return [
            'fullName' => trim(sprintf('%s %s', (string) $address->getFirstName(), (string) $address->getLastName())),
            'email' => (string) $order->getCustomer()?->getEmail(),
            'telephone' => (string) ($address->getPhoneNumber() ?? $order->getBillingAddress()?->getPhoneNumber() ?? ('' !== $fallbackTelephone ? $fallbackTelephone : '')),
            'street' => (string) $address->getStreet(),
            'postcode' => (string) $address->getPostcode(),
            'countryCode' => (string) $address->getCountryCode(),
        ];
    }
}
