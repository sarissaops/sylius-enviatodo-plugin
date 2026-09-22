<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Shipping;

use SarissaOps\Enviatodo\Model\Guide\GuideBinary;
use SarissaOps\Enviatodo\Model\Guide\InvalidFile;

/**
 * Merged result of chunked binaries downloads: usable files plus per-file
 * failures the API reports on HTTP 200.
 */
final class LabelBatch
{
    /**
     * @param list<GuideBinary> $files
     * @param list<InvalidFile> $invalidFiles
     */
    public function __construct(
        private readonly array $files,
        private readonly array $invalidFiles,
    ) {
    }

    /** @return list<GuideBinary> */
    public function getFiles(): array
    {
        return $this->files;
    }

    /** @return list<InvalidFile> */
    public function getInvalidFiles(): array
    {
        return $this->invalidFiles;
    }
}
