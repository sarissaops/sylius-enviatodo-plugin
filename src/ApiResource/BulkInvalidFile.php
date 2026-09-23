<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final class BulkInvalidFile
{
    public function __construct(
        #[Groups(['enviatodo:admin:bulk_labels:show'])]
        public int $guideId,
        #[Groups(['enviatodo:admin:bulk_labels:show'])]
        public string $reason,
    ) {
    }
}
