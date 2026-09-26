<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Component\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ShipmentContact;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;

final class ShipmentContactTest extends TestCase
{
    private function shipment(?string $shippingPhone, ?string $billingPhone): ShipmentInterface
    {
        $shipping = $this->createMock(AddressInterface::class);
        $shipping->method('getFirstName')->willReturn('John');
        $shipping->method('getLastName')->willReturn('Doe');
        $shipping->method('getPhoneNumber')->willReturn($shippingPhone);
        $shipping->method('getStreet')->willReturn('Paseo Violetas 10');
        $shipping->method('getPostcode')->willReturn('06600');
        $shipping->method('getCountryCode')->willReturn('MX');
        $billing = $this->createMock(AddressInterface::class);
        $billing->method('getPhoneNumber')->willReturn($billingPhone);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('john@example.com');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($shipping);
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getCustomer')->willReturn($customer);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }

    public function testExtractsContactFromShippingAddress(): void
    {
        $contact = ShipmentContact::fromShipment($this->shipment('5555555555', null), '');

        $this->assertSame('John Doe', $contact['fullName']);
        $this->assertSame('john@example.com', $contact['email']);
        $this->assertSame('5555555555', $contact['telephone']);
        $this->assertSame('Paseo Violetas 10', $contact['street']);
        $this->assertSame('06600', $contact['postcode']);
        $this->assertSame('MX', $contact['countryCode']);
    }

    public function testFallsBackToBillingThenConfiguredTelephone(): void
    {
        $this->assertSame('1111111111', ShipmentContact::fromShipment($this->shipment(null, '1111111111'), '')['telephone']);
        $this->assertSame('2222222222', ShipmentContact::fromShipment($this->shipment(null, null), '2222222222')['telephone']);
    }
}
