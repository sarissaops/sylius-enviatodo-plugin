<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\EventListener\OrderPaidListener;
use SarissaOps\SyliusEnviatodoPlugin\Exception\EnviatodoOrderException;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoOrderManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

final class OrderPaidListenerTest extends TestCase
{
    /** @return array{EnviatodoOrderManagerInterface, EnviatodoShipmentRepositoryInterface, LoggerInterface, OrderPaidListener} */
    private function listener(): array
    {
        $manager = $this->createMock(EnviatodoOrderManagerInterface::class);
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        return [$manager, $repository, $logger, new OrderPaidListener(
            $manager,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $repository,
            $logger,
        )];
    }

    private function orderWithMethod(string $methodCode): OrderInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($method);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        return $order;
    }

    private function event(object $subject): CompletedEvent
    {
        $transition = new Transition('pay', 'awaiting_payment', 'paid');

        return new CompletedEvent(
            $subject,
            new Marking(['paid' => 1]),
            $transition,
            new Workflow(new Definition(['awaiting_payment', 'paid'], [$transition]), new MethodMarkingStore()),
        );
    }

    public function testIgnoresNonOrderSubjects(): void
    {
        [$manager, , , $listener] = $this->listener();
        $manager->expects($this->never())->method('completeDraft');

        $listener($this->event(new \stdClass()));

        $this->addToAssertionCount(1);
    }

    public function testCompletesDraftRowsOfBackedShipments(): void
    {
        [$manager, $repository, , $listener] = $this->listener();
        $draft = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $repository->method('findOneByShipment')->willReturn($draft);
        $manager->expects($this->once())->method('completeDraft')->with($draft);

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta')));
    }

    public function testSkipsShipmentsWithoutDraftRowsOrOutsideTheMap(): void
    {
        [$manager, $repository, , $listener] = $this->listener();
        $repository->method('findOneByShipment')->willReturn(null);
        $manager->expects($this->never())->method('completeDraft');

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta')));
        $listener($this->event($this->orderWithMethod('flat_rate')));
    }

    public function testSkipsAlreadyCompletedDrafts(): void
    {
        [$manager, $repository, , $listener] = $this->listener();
        $draft = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $draft->complete('TRK1', 23200);
        $repository->method('findOneByShipment')->willReturn($draft);
        $manager->expects($this->never())->method('completeDraft');

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta')));
    }

    public function testSurvivesPurchaseFailuresWithAnErrorLog(): void
    {
        [$manager, $repository, $logger, $listener] = $this->listener();
        $draft = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $repository->method('findOneByShipment')->willReturn($draft);
        $manager->method('completeDraft')->willThrowException(new EnviatodoOrderException('empty balance'));
        $logger->expects($this->once())->method('error');

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta')));

        $this->addToAssertionCount(1);
    }
}
