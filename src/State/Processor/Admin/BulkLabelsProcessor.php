<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\State\Processor\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkInvalidFile;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelFile;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelsRequest;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelsResult;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
        $skipped = 0;
        foreach (array_unique($data->shipmentIds) as $shipmentId) {
            $guideId = $this->repository->findOneByShipmentId($shipmentId)?->getGuideId();
            if (null !== $guideId && is_numeric($guideId)) {
                $guideIds[] = (int) $guideId;
            } else {
                ++$skipped;
            }
        }
        if ([] === $guideIds) {
            throw new UnprocessableEntityHttpException('No downloadable labels found for the given shipments.');
        }
        if (0 < $skipped) {
            $this->logger->info('Enviatodo bulk labels skipped shipments without guides.', ['skipped' => $skipped]);
        }

        try {
            $batch = $this->labelService->downloadBinaries($guideIds);
        } catch (EnviatodoException | HttpClientException | HttpServerException | \RuntimeException $exception) {
            $this->logger->error('Enviatodo bulk label download failed.', ['error' => $exception->getMessage(), 'guides' => \count($guideIds)]);

            throw new HttpException(502, 'Label provider unavailable.', $exception);
        }

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
