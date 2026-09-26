<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Component\Shipping;

/**
 * Sylius shipping-method code -> Enviatodo provider/service pair.
 *
 * @phpstan-type ProviderService array{provider_id: string, provider_service_id: string}
 */
final class ProviderMap
{
    /** @var array<string, ProviderService> */
    private array $map = [];

    /**
     * @param array<mixed> $map method code -> provider/service pair
     */
    public function __construct(array $map)
    {
        foreach ($map as $methodCode => $row) {
            if (
                !\is_string($methodCode) || !\is_array($row) ||
                !isset($row['provider_id'], $row['provider_service_id']) ||
                !\is_string($row['provider_id']) || !\is_string($row['provider_service_id'])
            ) {
                throw new \InvalidArgumentException('Each provider map row needs string provider_id and provider_service_id values.');
            }
            // The API only understands numeric ids; a typo like 'EXPRESS'
            // would otherwise cast to 0 and silently mis-quote.
            if (!ctype_digit($row['provider_id']) || !ctype_digit($row['provider_service_id'])) {
                throw new \InvalidArgumentException(sprintf('Provider map row "%s" needs numeric provider_id and provider_service_id values.', $methodCode));
            }
            $this->map[$methodCode] = ['provider_id' => $row['provider_id'], 'provider_service_id' => $row['provider_service_id']];
        }
    }

    public function isBackedByEnviatodo(string $methodCode): bool
    {
        return isset($this->map[$methodCode]);
    }

    /**
     * @return ProviderService|null
     */
    public function getProviderService(string $methodCode): ?array
    {
        return $this->map[$methodCode] ?? null;
    }
}
