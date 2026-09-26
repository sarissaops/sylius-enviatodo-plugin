<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\State\Processor\Admin;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Order\CancelResponse;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\CancelShipmentRequest;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\ShipmentCanceller;
use SarissaOps\SyliusEnviatodoPlugin\State\Processor\Admin\ShipmentCancelProcessor;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShipmentCancelProcessorTest extends TestCase
{
    private function processor(EnviatodoShipmentRepositoryInterface $repository): ShipmentCancelProcessor
    {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->method('cancel')->willReturn(CancelResponse::create([]));
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->createMock(\Doctrine\ORM\EntityManagerInterface::class));

        return new ShipmentCancelProcessor(
            new ShipmentCanceller($orderApi, new NullLogger()),
            $repository,
            $registry,
            new NullLogger(),
        );
    }

    private function record(): EnviatodoShipment
    {
        $record = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $record->complete('TRK1', 23200);

        return $record;
    }

    public function testProcessCancelsAndMarksTheRecord(): void
    {
        $record = $this->record();
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $repository->method('findOneByShipmentId')->with(5)->willReturn($record);

        $result = $this->processor($repository)->process(
            new CancelShipmentRequest('7', 'QA cancel'),
            new \ApiPlatform\Metadata\Post(),
            ['shipmentId' => 5],
        );

        $this->assertSame(5, $result->shipmentId);
        $this->assertSame('TRK1', $result->trackingId);
        $this->assertSame(EnviatodoShipment::HANDOVER_CANCELLED, $record->getHandover());
    }

    public function testProcessReturns404WithoutALabel(): void
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $repository->method('findOneByShipmentId')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->processor($repository)->process(
            new CancelShipmentRequest('7', 'QA cancel'),
            new \ApiPlatform\Metadata\Post(),
            ['shipmentId' => 9],
        );
    }

    public function testProcessRejectsNonRequestPayloads(): void
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->processor($repository)->process(new \stdClass(), new \ApiPlatform\Metadata\Post(), ['shipmentId' => 5]);
    }

    public function testProcessMapsProviderFailuresToBadGateway(): void
    {
        $record = $this->record();
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $repository->method('findOneByShipmentId')->willReturn($record);
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->method('cancel')->willThrowException(\SarissaOps\Enviatodo\Exception\HttpServerException::serverError());
        $registry = $this->createMock(ManagerRegistry::class);
        $processor = new ShipmentCancelProcessor(
            new ShipmentCanceller($orderApi, new NullLogger()),
            $repository,
            $registry,
            new NullLogger(),
        );

        try {
            $processor->process(
                new CancelShipmentRequest('7', 'QA cancel'),
                new \ApiPlatform\Metadata\Post(),
                ['shipmentId' => 5],
            );
            $this->fail('Expected HttpException was not thrown.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }
}
