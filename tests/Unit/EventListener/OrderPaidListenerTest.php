<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\EventListener\OrderPaidListener;
use SarissaOps\SyliusEnviatodoPlugin\Exception\EnviatodoOrderException;
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
    /** @return array{EnviatodoOrderManagerInterface, LoggerInterface, OrderPaidListener} */
    private function listener(): array
    {
        $manager = $this->createMock(EnviatodoOrderManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        return [$manager, $logger, new OrderPaidListener(
            $manager,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
            $logger,
        )];
    }

    /** @param array<string, mixed> $configuration */
    private function orderWithMethod(string $methodCode, array $configuration = ['quote' => ['origin' => [], 'package' => []]]): OrderInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);
        $method->method('getConfiguration')->willReturn($configuration);
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
        [$manager, , $listener] = $this->listener();
        $manager->expects($this->never())->method('purchaseForPaidShipment');

        $listener($this->event(new \stdClass()));

        $this->addToAssertionCount(1);
    }

    public function testPurchasesLabelsForBackedShipments(): void
    {
        [$manager, , $listener] = $this->listener();
        $manager->expects($this->once())->method('purchaseForPaidShipment');

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta')));
    }

    public function testSkipsShipmentsOutsideTheMap(): void
    {
        [$manager, , $listener] = $this->listener();
        $manager->expects($this->never())->method('purchaseForPaidShipment');

        $listener($this->event($this->orderWithMethod('flat_rate')));
    }

    public function testSkipsMethodsWithoutQuoteConfiguration(): void
    {
        [$manager, $logger, $listener] = $this->listener();
        $manager->expects($this->never())->method('purchaseForPaidShipment');
        $logger->expects($this->once())->method('error');

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta', [])));
    }

    public function testLogsAndContinuesOnPurchaseFailure(): void
    {
        [$manager, $logger, $listener] = $this->listener();
        $manager->method('purchaseForPaidShipment')->willThrowException(new EnviatodoOrderException('empty balance'));
        $logger->expects($this->once())->method('error');

        $listener($this->event($this->orderWithMethod('enviatodo_estafeta')));

        $this->addToAssertionCount(1);
    }
}
