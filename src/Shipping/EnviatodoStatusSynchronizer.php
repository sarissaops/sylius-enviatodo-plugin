<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Order\Order;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\OrderStatusNames;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\StatusMap;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;

/**
 * Pulls order-history rows and advances local handover milestones.
 * Idempotent compare-and-set: only writes when the resolved key differs,
 * never regresses terminal states, never overwrites a merchant branch
 * drop-off, and skips unknown status names with a log line.
 */
final class EnviatodoStatusSynchronizer
{
    /** @var array<string, int> */
    private const RANK = [
        EnviatodoShipment::HANDOVER_LABEL_CREATED => 0,
        EnviatodoShipment::HANDOVER_PICKUP_PENDING => 1,
        EnviatodoShipment::HANDOVER_IN_PICKUP => 2,
        EnviatodoShipment::HANDOVER_DROPPED_AT_BRANCH => 3,
        EnviatodoShipment::HANDOVER_IN_TRANSIT => 4,
        EnviatodoShipment::HANDOVER_IN_RETURN => 4,
        EnviatodoShipment::HANDOVER_INCIDENT => 5,
        EnviatodoShipment::HANDOVER_PICKUP_RESCHEDULED => 5,
        EnviatodoShipment::HANDOVER_DELIVERED => 6,
        EnviatodoShipment::HANDOVER_CANCELLED => 6,
        EnviatodoShipment::HANDOVER_PICKUP_CANCELLED => 6,
    ];

    public function __construct(
        private readonly OrderApi $orderApi,
        private readonly StatusMap $statusMap,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
        private readonly ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Resolves history rows without writing: reports what sync() would do.
     *
     * @param array<string, mixed> $criteria
     *
     * @return list<array{tracking_id: string, status_name: string, code: ?int, handover: string}>
     */
    public function preview(array $criteria, ?int $limit = 100): array
    {
        $rows = $this->orderApi->filter($criteria + ['recipient' => null]);
        if (!\is_array($rows)) {
            return [];
        }

        $preview = [];
        $processed = 0;
        foreach ($rows as $row) {
            if (null !== $limit && $processed >= $limit) {
                break;
            }
            ++$processed;
            $code = OrderStatusNames::toCode($row->getStatusName());
            $detail = $row->getDetail();
            $guide = $detail['guide'] ?? null;
            $preview[] = [
                'tracking_id' => \is_array($guide) ? $this->stringOrEmpty($guide['tracking_id'] ?? null) : '',
                'status_name' => $row->getStatusName(),
                'code' => $code,
                'handover' => null === $code ? 'unknown' : $this->statusMap->forOrder($code),
            ];
        }

        return $preview;
    }

    /**
     * @param array<string, mixed> $criteria filter() criteria (date_range, provider, status, user_id)
     *
     * @return array{updated: int, skipped: int, unknown: int, failed: int}
     */
    public function sync(array $criteria, ?int $limit = 100): array
    {
        $summary = ['updated' => 0, 'skipped' => 0, 'unknown' => 0, 'failed' => 0];

        try {
            $rows = $this->orderApi->filter($criteria + ['recipient' => null]);
        } catch (\Exception $exception) {
            $this->logger->error('Enviatodo status sync failed.', ['error' => $exception->getMessage()]);

            $summary['failed'] = 1;

            return $summary;
        }
        if (!\is_array($rows)) {
            return $summary;
        }

        $processed = 0;
        foreach ($rows as $row) {
            if (null !== $limit && $processed >= $limit) {
                break;
            }
            ++$processed;

            try {
                $this->syncRow($row, $summary);
            } catch (\Exception $exception) {
                $this->logger->error('Enviatodo status row failed.', ['error' => $exception->getMessage()]);
                ++$summary['failed'];
            }
        }
        $this->registry->getManager()->flush();

        return $summary;
    }

    /**
     * @param array{updated: int, skipped: int, unknown: int, failed: int} $summary
     */
    private function syncRow(Order $row, array &$summary): void
    {
        $code = OrderStatusNames::toCode($row->getStatusName());
        if (null === $code) {
            $this->logger->warning('Enviatodo status name has no code mapping.', ['status_name' => $row->getStatusName()]);
            ++$summary['unknown'];

            return;
        }
            $key = $this->statusMap->forOrder($code);
            if ('unknown' === $key) {
                $key = $this->statusMap->forPickup($code);
            }
            if ('unknown' === $key) {
                ++$summary['unknown'];

                return;
            }
        $detail = $row->getDetail();
        $guide = $detail['guide'] ?? null;
        $trackingId = \is_array($guide) ? $this->stringOrEmpty($guide['tracking_id'] ?? null) : '';
        if ('' === $trackingId) {
            ++$summary['skipped'];

            return;
        }
        $record = $this->repository->findOneByTrackingId($trackingId);
        if (null === $record) {
            ++$summary['skipped'];

            return;
        }
            if (!$this->mayAdvance($record->getHandover(), $key)) {
                ++$summary['skipped'];

                return;
            }
            // Creation is the starting state: nothing to write, and counting
            // it as updated would lie in the summary.
            if ('label_created' === $key) {
                ++$summary['skipped'];

                return;
            }
            $this->apply($record, $key, $row->getStatusName());
            ++$summary['updated'];
    }

    private function stringOrEmpty(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    private function mayAdvance(string $current, string $next): bool
    {
        if ($current === $next) {
            return false;
        }
        // Branch drop-off is a merchant action: sync never overwrites it.
        if (EnviatodoShipment::HANDOVER_DROPPED_AT_BRANCH === $current) {
            return false;
        }
        $currentRank = self::RANK[$current] ?? 0;
        $nextRank = self::RANK[$next] ?? 0;
        if (6 <= $currentRank) {
            // Terminal states never move, not even laterally.
            return false;
        }

        return $nextRank >= $currentRank;
    }

    private function apply(EnviatodoShipment $record, string $key, string $statusName): void
    {
        match ($key) {
            EnviatodoShipment::HANDOVER_IN_PICKUP => $record->markInPickup(),
            // A generated pickup is actionable the same way as an in-pickup one.
            'pickup_created' => $record->markInPickup(),
            EnviatodoShipment::HANDOVER_IN_TRANSIT => $record->markInTransit(),
            EnviatodoShipment::HANDOVER_DELIVERED => $record->markDelivered(),
            EnviatodoShipment::HANDOVER_CANCELLED => $record->markCancelled(),
            EnviatodoShipment::HANDOVER_IN_RETURN => $record->markInReturn(),
            EnviatodoShipment::HANDOVER_INCIDENT => $record->markIncident($statusName),
            EnviatodoShipment::HANDOVER_PICKUP_PENDING => $record->markPickupPending(),
            EnviatodoShipment::HANDOVER_PICKUP_CANCELLED => $record->markPickupCancelled(),
            EnviatodoShipment::HANDOVER_PICKUP_RESCHEDULED => $record->markPickupRescheduled($statusName),
            default => null,
        };
    }
}
