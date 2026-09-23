<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final class BulkLabelsResult
{
    /** @param list<BulkLabelFile> $files @param list<BulkInvalidFile> $invalidFiles */
    public function __construct(
        #[Groups(['enviatodo:admin:bulk_labels:show'])]
        public array $files = [],
        #[Groups(['enviatodo:admin:bulk_labels:show'])]
        public array $invalidFiles = [],
    ) {
    }
}
