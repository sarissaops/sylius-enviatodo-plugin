<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\AddressApi;
use SarissaOps\Enviatodo\Model\Address\Address;
use SarissaOps\Enviatodo\Model\Address\SavedAddress;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoAddressBook;

final class EnviatodoAddressBookTest extends TestCase
{
    /** @return array<string, mixed> */
    private function addressData(string $id = '12', string $type = '1'): array
    {
        return ['id' => $id, 'address_type_id' => $type, 'full_name' => 'HQ', 'company' => 'Acme', 'zip_code' => '64000', 'default_addr' => '1'];
    }

    public function testOriginsListsTypeOneAddresses(): void
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->expects($this->once())->method('byType')->with(1)->willReturn([Address::create($this->addressData())]);

        $origins = (new EnviatodoAddressBook($addressApi, new NullLogger()))->origins();

        $this->assertCount(1, $origins);
        $this->assertSame('12', $origins[0]->getId());
    }

    public function testDestinationsListsTypeTwoAddresses(): void
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->expects($this->once())->method('byType')->with(2)->willReturn([Address::create($this->addressData('13', '2'))]);

        $destinations = (new EnviatodoAddressBook($addressApi, new NullLogger()))->destinations();

        $this->assertCount(1, $destinations);
        $this->assertSame('2', $destinations[0]->getAddressTypeId());
    }

    public function testGetReturnsTheFirstMatch(): void
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->expects($this->once())->method('show')->with(12)->willReturn([Address::create($this->addressData())]);

        $address = (new EnviatodoAddressBook($addressApi, new NullLogger()))->get(12);

        $this->assertNotNull($address);
        $this->assertSame('HQ', $address->getFullName());
    }

    public function testSaveReturnsTheStoredAddress(): void
    {
        $data = $this->addressData();
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->expects($this->once())->method('save')->with($data)->willReturn(SavedAddress::create(['address' => $data]));

        $saved = (new EnviatodoAddressBook($addressApi, new NullLogger()))->save($data);

        $this->assertSame('Acme', $saved->getCompany());
    }

    public function testSaveRejectsNonModelHydration(): void
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->method('save')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        (new EnviatodoAddressBook($addressApi, new NullLogger()))->save($this->addressData());
    }

    public function testRemoveDeletesById(): void
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->expects($this->once())->method('delete')->with(12);

        (new EnviatodoAddressBook($addressApi, new NullLogger()))->remove(12);

        $this->addToAssertionCount(1);
    }
}
