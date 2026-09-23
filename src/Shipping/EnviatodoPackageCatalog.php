<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\PackageApi;
use SarissaOps\Enviatodo\Model\Package\Package;
use SarissaOps\Enviatodo\Model\Package\SavedPackage;

/**
 * Merchant parcel presets. Exactly one row may carry the default flag used
 * for checkout quotes.
 */
final class EnviatodoPackageCatalog
{
    public function __construct(
        private readonly PackageApi $packageApi,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<Package> */
    public function all(): array
    {
        $result = $this->packageApi->all();
        if (!\is_array($result)) {
            throw new \RuntimeException('PackageApi must hydrate Package models for listing.');
        }

        return $result;
    }

    public function get(int $id): ?Package
    {
        $result = $this->packageApi->show($id);
        if (!\is_array($result)) {
            throw new \RuntimeException('PackageApi must hydrate Package models for fetching.');
        }
        $first = $result[0] ?? null;

        return $first instanceof Package ? $first : null;
    }

    public function defaultPackage(): ?Package
    {
        foreach ($this->all() as $package) {
            if ('1' === $package->getDefaultPkg()) {
                return $package;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): Package
    {
        $result = $this->packageApi->save($data);
        if (!$result instanceof SavedPackage) {
            throw new \RuntimeException('PackageApi must hydrate SavedPackage models for saving.');
        }

        $this->logger->info('Enviatodo package saved.', ['id' => $result->getPackage()->getId()]);

        return $result->getPackage();
    }

    public function remove(int $id): void
    {
        $this->packageApi->delete($id);

        $this->logger->info('Enviatodo package deleted.', ['id' => $id]);
    }
}
