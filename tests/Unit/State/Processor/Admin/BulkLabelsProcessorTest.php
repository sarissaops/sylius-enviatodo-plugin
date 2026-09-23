<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\State\Processor\Admin;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\GuideApi;
use SarissaOps\Enviatodo\Model\Guide\GuideBinaries;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\BulkLabelsRequest;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;
use SarissaOps\SyliusEnviatodoPlugin\State\Processor\Admin\BulkLabelsProcessor;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class BulkLabelsProcessorTest extends TestCase
{
    private function processor(EnviatodoShipmentRepositoryInterface $repository): BulkLabelsProcessor
    {
        $guideApi = $this->createMock(GuideApi::class);
        $guideApi->method('binaries')->willReturn(GuideBinaries::create([
            'files' => [['guide_id' => 7, 'name' => 'label.pdf', 'binary' => 'Ym9keQ==', 'mime' => 'application/pdf']],
            'invalid_files' => [],
        ]));

        return new BulkLabelsProcessor(new EnviatodoLabelService($guideApi), $repository, new NullLogger());
    }

    public function testProcessDownloadsLabelsForStoredGuideIds(): void
    {
        $record = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $record->setGuideId('7');
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(
            static fn (int $id): ?EnviatodoShipment => 1 === $id ? $record : null,
        );

        $result = $this->processor($repository)->process(
            new BulkLabelsRequest([1, 2]),
            new \ApiPlatform\Metadata\Post(),
        );

        $this->assertCount(1, $result->files);
        $this->assertSame(7, $result->files[0]->guideId);
        $this->assertCount(0, $result->invalidFiles);
    }

    public function testProcessReturnsEmptyResultWithoutGuideIds(): void
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $result = $this->processor($repository)->process(
            new BulkLabelsRequest([9]),
            new \ApiPlatform\Metadata\Post(),
        );

        $this->assertCount(0, $result->files);
    }

    public function testProcessRejectsNonRequestPayloads(): void
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->processor($repository)->process(new \stdClass(), new \ApiPlatform\Metadata\Post());
    }
}
