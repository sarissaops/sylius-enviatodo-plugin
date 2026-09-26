<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\State\Processor\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\CancelledShipmentResult;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\CancelShipmentRequest;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\ShipmentCanceller;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Headless label cancellation for one shipment. Mirrors the admin MVC
 * controller: resolves the record by Sylius shipment id, cancels by
 * tracking id, persists the cancelled milestone.
 *
 * @implements ProcessorInterface<CancelShipmentRequest, CancelledShipmentResult>
 */
final class ShipmentCancelProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShipmentCanceller $canceller,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
        private readonly ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CancelledShipmentResult
    {
        // @phpstan-ignore instanceof.alwaysTrue (the template binds the contract; runtime callers may pass anything)
        if (!$data instanceof CancelShipmentRequest) {
            throw new \InvalidArgumentException('Shipment cancellation requires a CancelShipmentRequest payload.');
        }
        $rawId = $uriVariables['shipmentId'] ?? null;
        $shipmentId = \is_int($rawId) ? $rawId : (\is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0);
        $record = 0 < $shipmentId ? $this->repository->findOneByShipmentId($shipmentId) : null;
        $trackingId = $record?->getTrackingId();
        if (null === $trackingId || '' === $trackingId) {
            throw new NotFoundHttpException('Shipment not found.');
        }

        try {
            $window = $this->canceller->cancel([$trackingId], $data->reasonValue, $data->reasonText);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(422, 'Cancellation rejected by the label provider.', $exception);
        } catch (EnviatodoException | HttpClientException | HttpServerException | \RuntimeException $exception) {
            $this->logger->error('Enviatodo label cancellation failed.', ['tracking_id' => $trackingId, 'error' => $exception->getMessage()]);

            throw new HttpException(502, 'Label provider unavailable.', $exception);
        }

        $record->markCancelled();
        $this->registry->getManager()->flush();

        return new CancelledShipmentResult($shipmentId, $trackingId, $window['cancelled_at'], $window['refunded_at']);
    }
}
