<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface as CoreShipmentInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

/**
 * Single extraction point from a Sylius shipment to the primitives the
 * quote API needs. Framework-coupled seam (Sylius types in, plain
 * strings out) shared by the checkout calculator and the paid-order
 * label purchase, so both quote the same destination.
 */
final class ShipmentContact
{
    /**
     * Shop checkout collects no phone: callers pass the method-level
     * `contact.telephone` fallback when the addresses carry none.
     *
     * @param array<string, mixed> $quoteConfig
     */
    public static function fallbackTelephone(array $quoteConfig): string
    {
        $contact = $quoteConfig['contact'] ?? null;
        if (!\is_array($contact) || !isset($contact['telephone']) || !\is_scalar($contact['telephone'])) {
            return '';
        }

        return trim((string) $contact['telephone']);
    }

    /**
     * @return array{fullName: string, email: string, telephone: string, street: string, postcode: string, countryCode: string}
     */
    public static function fromShipment(ShipmentInterface $subject, string $fallbackTelephone = ''): array
    {
        if (!$subject instanceof CoreShipmentInterface) {
            throw new NoRateAvailableException('Enviatodo quoting requires a core shipment carrying the order.');
        }
        $order = $subject->getOrder();
        if (!$order instanceof CoreOrderInterface) {
            throw new NoRateAvailableException('Enviatodo quoting requires a shipment attached to an order.');
        }
        $address = $order->getShippingAddress();
        if (null === $address) {
            throw new NoRateAvailableException('Enviatodo quoting requires an order shipping address.');
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
