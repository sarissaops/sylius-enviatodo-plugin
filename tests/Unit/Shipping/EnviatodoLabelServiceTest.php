<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\GuideApi;
use SarissaOps\Enviatodo\Model\Guide\GuideBinaries;
use SarissaOps\Enviatodo\Model\Guide\GuideFile;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;

final class EnviatodoLabelServiceTest extends TestCase
{
    private function binaries(bool $withInvalid): GuideBinaries
    {
        return GuideBinaries::create([
            'files' => [['guide_id' => 7, 'name' => 'label.pdf', 'binary' => 'Ym9keQ==', 'mime' => 'application/pdf']],
            'invalid_files' => $withInvalid ? [['guide_id' => 8, 'reason' => 'missing', 'binary' => 'false']] : [],
        ]);
    }

    public function testDownloadLabelReturnsTheFileId(): void
    {
        $guideApi = $this->createMock(GuideApi::class);
        $guideApi->expects($this->once())->method('download')->with([7])->willReturn(GuideFile::create(['file_id' => 'f1']));

        $this->assertSame('f1', (new EnviatodoLabelService($guideApi))->downloadLabel(7));
    }

    public function testDownloadLabelRejectsNonModelHydration(): void
    {
        $guideApi = $this->createMock(GuideApi::class);
        $guideApi->method('download')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        (new EnviatodoLabelService($guideApi))->downloadLabel(7);
    }

    public function testDownloadBinariesChunksAndKeepsInvalidFiles(): void
    {
        $guideApi = $this->createMock(GuideApi::class);
        $guideApi->expects($this->exactly(2))->method('binaries')->willReturn($this->binaries(true));

        $result = (new EnviatodoLabelService($guideApi))->downloadBinaries([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);

        $this->assertCount(2, $result->getFiles());
        $this->assertCount(2, $result->getInvalidFiles());
        $this->assertSame(7, $result->getFiles()[0]->getGuideId());
        $this->assertSame('missing', $result->getInvalidFiles()[0]->getReason());
    }

    public function testDownloadBinariesRejectsEmptyLists(): void
    {
        $guideApi = $this->createMock(GuideApi::class);
        $guideApi->expects($this->never())->method('binaries');

        $this->expectException(\InvalidArgumentException::class);

        (new EnviatodoLabelService($guideApi))->downloadBinaries([]);
    }
}
