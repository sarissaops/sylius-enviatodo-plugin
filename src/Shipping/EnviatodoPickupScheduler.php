<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\PickupApi;
use SarissaOps\Enviatodo\Model\Pickup\PickupOrder;
use SarissaOps\Enviatodo\Model\Pickup\PickupResponse;

/**
 * Dock pickup scheduling. One call books a whole same-origin run; mixed
 * origins are rejected before any traffic because the API would refuse them.
 */
final class EnviatodoPickupScheduler
{
    /** @var list<string> */
    private const CONTACT_KEYS = ['who_delivers', 'contact_phone', 'contact_email'];

    public function __construct(
        private readonly PickupApi $pickupApi,
        private readonly LoggerInterface $logger,
        private readonly bool $autoSchedule,
    ) {
    }

    public function isAutoScheduleEnabled(): bool
    {
        return $this->autoSchedule;
    }

    /**
     * @param list<string> $providerIds
     *
     * @return list<PickupOrder>
     */
    public function eligibleOrders(array $providerIds): array
    {
        if ([] === $providerIds) {
            throw new \InvalidArgumentException('Pickup lookup requires at least one provider id.');
        }

        $result = $this->pickupApi->ordersForPickup($providerIds);
        if (!\is_array($result)) {
            throw new \RuntimeException('PickupApi must hydrate PickupOrder models for queue listing.');
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $pickupData
     */
    public function schedule(array $pickupData): bool
    {
        foreach (self::CONTACT_KEYS as $key) {
            $value = $pickupData[$key] ?? null;
            if (!\is_string($value) || '' === $value) {
                throw new \InvalidArgumentException(sprintf('Pickup requires handover contact "%s".', $key));
            }
        }

        $rows = $pickupData['data'] ?? null;
        if (!\is_array($rows) || [] === $rows) {
            throw new \InvalidArgumentException('Pickup requires at least one order row.');
        }
        $origins = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                throw new \InvalidArgumentException('Pickup order rows must be arrays.');
            }
            $origin = $row['origin_address'] ?? null;
            if (!\is_string($origin) || '' === $origin) {
                throw new \InvalidArgumentException('Pickup order rows require an origin_address.');
            }
            $origins[$origin] = true;
        }
        if (\count($origins) > 1) {
            throw new \InvalidArgumentException('All guides in one pickup must share the same origin address.');
        }

        $result = $this->pickupApi->create($pickupData);
        if (!$result instanceof PickupResponse) {
            throw new \RuntimeException('PickupApi must hydrate PickupResponse models for scheduling.');
        }

        $this->logger->info('Enviatodo pickup scheduled.', ['origins' => array_keys($origins)]);

        return $result->isCreated();
    }

    public function cancelPickup(int $id, string $description, string $userId): bool
    {
        $result = $this->pickupApi->cancel($id, $description, $userId);
        if (!$result instanceof PickupResponse) {
            throw new \RuntimeException('PickupApi must hydrate PickupResponse models for cancellation.');
        }

        $this->logger->info('Enviatodo pickup cancelled.', ['id' => $id, 'user_id' => $userId]);

        return $result->isCreated();
    }
}
