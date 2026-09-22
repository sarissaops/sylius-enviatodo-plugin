<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\Enviatodo\Api\GuideApi;
use SarissaOps\Enviatodo\Model\Guide\GuideBinaries;
use SarissaOps\Enviatodo\Model\Guide\GuideFile;

/**
 * Label file retrieval. Binaries download in chunks of 10 (API batch limit)
 * and merge per-file failures instead of failing the whole batch.
 */
final class EnviatodoLabelService
{
    private const BATCH_SIZE = 10;

    public function __construct(private readonly GuideApi $guideApi)
    {
    }

    public function downloadLabel(int $guideId): string
    {
        $result = $this->guideApi->download([$guideId]);
        if (!$result instanceof GuideFile) {
            throw new \RuntimeException('GuideApi must hydrate GuideFile models for label download.');
        }

        return $result->getFileId();
    }

    /**
     * @param list<int> $guideIds
     */
    public function downloadBinaries(array $guideIds): LabelBatch
    {
        if ([] === $guideIds) {
            throw new \InvalidArgumentException('Label download requires at least one guide id.');
        }

        $files = [];
        $invalidFiles = [];
        foreach (array_chunk($guideIds, self::BATCH_SIZE) as $chunk) {
            $result = $this->guideApi->binaries($chunk);
            if (!$result instanceof GuideBinaries) {
                throw new \RuntimeException('GuideApi must hydrate GuideBinaries models for bulk download.');
            }
            array_push($files, ...$result->getFiles());
            array_push($invalidFiles, ...$result->getInvalidFiles());
        }

        return new LabelBatch($files, $invalidFiles);
    }
}
