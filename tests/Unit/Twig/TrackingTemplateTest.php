<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TrackingTemplateTest extends TestCase
{
    private function render(array $context): string
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../templates/admin/order/tracking.html.twig');
        $twig = new Environment(new ArrayLoader(['tracking.html.twig' => $source]));

        $shipment = $this->createMock(ShipmentInterface::class);
        $record = new EnviatodoShipment($shipment, 'uuid-1', '9', '11');
        $record->complete('TRK1', 23200);
        $twig->addFunction(new \Twig\TwigFunction('enviatodo_shipment_record', static fn (): EnviatodoShipment => $record));

        return $twig->render('tracking.html.twig', ['hookable_metadata' => ['context' => $context]]);
    }

    public function testRendersFromOrderContext(): void
    {
        $order = new \stdClass();
        $order->shipments = [$this->createMock(ShipmentInterface::class)];

        $html = $this->render(['order' => $order]);

        $this->assertStringContainsString('TRK1', $html);
    }

    public function testRendersFromResourceContext(): void
    {
        $order = new \stdClass();
        $order->shipments = [$this->createMock(ShipmentInterface::class)];

        $html = $this->render(['resource' => $order]);

        $this->assertStringContainsString('TRK1', $html);
    }

    public function testRendersEmptyWithoutOrderOrResource(): void
    {
        $html = $this->render([]);

        $this->assertStringNotContainsString('TRK1', $html);
    }
}
