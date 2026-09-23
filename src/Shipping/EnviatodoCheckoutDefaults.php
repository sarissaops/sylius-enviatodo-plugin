<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\Enviatodo\Model\Address\Address;
use SarissaOps\Enviatodo\Model\Package\Package;

/**
 * Checkout defaults from plugin configuration. Null ids resolve to null —
 * explicit configuration or nothing, never a guessed address.
 */
final class EnviatodoCheckoutDefaults
{
    public function __construct(
        private readonly EnviatodoAddressBook $addressBook,
        private readonly EnviatodoPackageCatalog $packageCatalog,
        private readonly ?int $originAddressId,
        private readonly ?int $packageId,
    ) {
    }

    public function originAddress(): ?Address
    {
        if (null === $this->originAddressId) {
            return null;
        }

        return $this->addressBook->get($this->originAddressId);
    }

    public function defaultPackage(): ?Package
    {
        if (null === $this->packageId) {
            return null;
        }

        return $this->packageCatalog->get($this->packageId);
    }
}
