<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class BulkLabelsRequest
{
    /** @param list<int> $shipmentIds */
    public function __construct(
        #[Groups(['enviatodo:admin:bulk_labels:write'])]
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public array $shipmentIds = [],
    ) {
    }
}
