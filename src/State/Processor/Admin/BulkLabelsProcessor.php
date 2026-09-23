<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\State\Processor\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Psr\Log\LoggerInterface;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkInvalidFile;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelFile;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelsRequest;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelsResult;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;

/**
 * Headless bulk label download. Mirrors the admin AJAX controller over the
 * same label service; per-file failures ride along instead of failing the
 * whole batch.
 *
 * @implements ProcessorInterface<BulkLabelsRequest, BulkLabelsResult>
 */
final class BulkLabelsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EnviatodoLabelService $labelService,
        private readonly EnviatodoShipmentRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BulkLabelsResult
    {
        if (!$data instanceof BulkLabelsRequest) {
            throw new \InvalidArgumentException('Bulk labels require a BulkLabelsRequest payload.');
        }

        $guideIds = [];
        foreach ($data->shipmentIds as $shipmentId) {
            $guideId = $this->repository->findById($shipmentId)?->getGuideId();
            if (null !== $guideId && is_numeric($guideId)) {
                $guideIds[] = (int) $guideId;
            }
        }
        if ([] === $guideIds) {
            return new BulkLabelsResult([], []);
        }

        $batch = $this->labelService->downloadBinaries($guideIds);

        $files = [];
        foreach ($batch->getFiles() as $file) {
            $files[] = new BulkLabelFile($file->getGuideId(), $file->getName(), $file->getBinary(), $file->getMime());
        }
        $invalidFiles = [];
        foreach ($batch->getInvalidFiles() as $invalidFile) {
            $invalidFiles[] = new BulkInvalidFile($invalidFile->getGuideId(), $invalidFile->getReason());
        }

        $this->logger->info('Enviatodo bulk labels downloaded.', ['count' => \count($files)]);

        return new BulkLabelsResult($files, $invalidFiles);
    }
}
