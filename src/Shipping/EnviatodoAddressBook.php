<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\AddressApi;
use SarissaOps\Enviatodo\Model\Address\Address;
use SarissaOps\Enviatodo\Model\Address\SavedAddress;

/**
 * Merchant address book. Type 1 rows are origins (branches, HQ default),
 * type 2 rows are destination presets.
 */
final class EnviatodoAddressBook
{
    public function __construct(
        private readonly AddressApi $addressApi,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<Address> */
    public function origins(): array
    {
        return $this->byType(1);
    }

    /** @return list<Address> */
    public function destinations(): array
    {
        return $this->byType(2);
    }

    /** @return list<Address> */
    public function all(): array
    {
        $result = $this->addressApi->all();
        if (!\is_array($result)) {
            throw new \RuntimeException('AddressApi must hydrate Address models for listing.');
        }

        return $result;
    }

    public function get(int $id): ?Address
    {
        $result = $this->addressApi->show($id);
        if (!\is_array($result)) {
            throw new \RuntimeException('AddressApi must hydrate Address models for fetching.');
        }
        $first = $result[0] ?? null;

        return $first instanceof Address ? $first : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): Address
    {
        $result = $this->addressApi->save($data);
        if (!$result instanceof SavedAddress) {
            throw new \RuntimeException('AddressApi must hydrate SavedAddress models for saving.');
        }

        $this->logger->info('Enviatodo address saved.', ['id' => $result->getAddress()->getId()]);

        return $result->getAddress();
    }

    public function remove(int $id): void
    {
        $this->addressApi->delete($id);

        $this->logger->info('Enviatodo address deleted.', ['id' => $id]);
    }

    /** @return list<Address> */
    private function byType(int $type): array
    {
        $result = $this->addressApi->byType($type);
        if (!\is_array($result)) {
            throw new \RuntimeException('AddressApi must hydrate Address models for type listing.');
        }

        return $result;
    }
}
