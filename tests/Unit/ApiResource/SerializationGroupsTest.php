<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\ApiResource;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkInvalidFile;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelFile;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelsResult;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRate;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRatesResult;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Pins that nested DTO rows render under the show groups used by the
 * api_resources normalization contexts — and vanish under other groups.
 */
final class SerializationGroupsTest extends TestCase
{
    private function serializer(): Serializer
    {
        $normalizer = new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()));

        return new Serializer([$normalizer]);
    }

    /** @return array<string, mixed> */
    private function liveRates(): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $this->serializer()->normalize(
            new LiveRatesResult('uuid-1', [
                new LiveRate('9', '11', 'ESTAFETA', 'Aéreo', 23200, 'MXN', '2026-01-03'),
            ]),
            null,
            ['groups' => ['enviatodo:shop:live_rates:show']],
        );

        return $normalized;
    }

    public function testLiveRatesNestRowsUnderShowGroup(): void
    {
        $normalized = $this->liveRates();

        $this->assertSame('uuid-1', $normalized['transactionUuid']);
        /** @var list<array<string, mixed>> $rates */
        $rates = $normalized['rates'];
        $this->assertCount(1, $rates);
        $this->assertSame('ESTAFETA', $rates[0]['providerName']);
        $this->assertSame(23200, $rates[0]['amountMinor']);
    }

    public function testLiveRatesHideRowsUnderForeignGroups(): void
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $this->serializer()->normalize(
            new LiveRatesResult('uuid-1', [
                new LiveRate('9', '11', 'ESTAFETA', 'Aéreo', 23200, 'MXN', '2026-01-03'),
            ]),
            null,
            ['groups' => ['sylius:shop:cart:show']],
        );

        $this->assertSame([], $normalized);
    }

    public function testBulkLabelsNestFilesUnderShowGroup(): void
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $this->serializer()->normalize(
            new BulkLabelsResult(
                [new BulkLabelFile(7, 'label.pdf', 'Ym9keQ==', 'application/pdf')],
                [new BulkInvalidFile(8, 'missing')],
            ),
            null,
            ['groups' => ['enviatodo:admin:bulk_labels:show']],
        );

        /** @var list<array<string, mixed>> $files */
        $files = $normalized['files'];
        $this->assertCount(1, $files);
        $this->assertSame('label.pdf', $files[0]['name']);
        /** @var list<array<string, mixed>> $invalidFiles */
        $invalidFiles = $normalized['invalidFiles'];
        $this->assertCount(1, $invalidFiles);
        $this->assertSame('missing', $invalidFiles[0]['reason']);
    }
}
