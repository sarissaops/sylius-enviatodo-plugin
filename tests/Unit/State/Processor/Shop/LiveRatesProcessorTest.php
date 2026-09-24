<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\State\Processor\Shop;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRatesQuoteRequest;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\State\Processor\Shop\LiveRatesProcessor;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LiveRatesProcessorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function quotePayload(): array
    {
        return [
            'origin' => ['zip_code' => '64000'],
            'destination' => ['zip_code' => '06600'],
            'package' => [
                'product_type' => '01010101', 'unit_type' => 'X1A', 'package_content' => 'books',
                'amount_pkg' => '200', 'height' => 10, 'width' => 10, 'length' => 10, 'weight' => 1,
                'real_weight' => '1.00', 'volumetric_weight' => '1', 'bill_weight' => '1', 'default_pkg' => '0',
            ],
        ];
    }

    /** @return array{OrderRepositoryInterface, ChannelContextInterface, EnviatodoCalculator, LiveRatesProcessor} */
    private function processor(): array
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB');
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('all')->willReturn(Quote::create([
            'transaction' => ['type' => 'order', 'timestamp' => '2026-01-01', 'uuid' => 'uuid-1'],
            'packages' => ['quantity' => 1],
            'rates' => [
                [
                    'provider_id' => '9', 'provider_service_id' => '11', 'provider_name' => 'ESTAFETA',
                    'service_name' => 'Aéreo', 'status' => true,
                    'charges' => [['type' => 'base', 'sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00']],
                    'detail_charges' => [['currency' => 'MXN']],
                ],
            ],
        ]));

        $calculator = new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class));
        $processor = new LiveRatesProcessor(
            $orderRepository,
            $channelContext,
            $calculator,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );

        return [$orderRepository, $channelContext, $calculator, $processor];
    }

    private function orderWithShipment(int $shipmentId, string $methodCode, string $channelCode = 'WEB', string $checkoutState = 'cart'): OrderInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getId')->willReturn($shipmentId);
        $shipment->method('getMethod')->willReturn($method);
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($channelCode);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCheckoutState')->willReturn($checkoutState);

        return $order;
    }

    /** @return array<string, string> */
    private function uriVariables(): array
    {
        return ['tokenValue' => 'cart-token', 'shipmentId' => '5'];
    }

    public function testProcessReturnsUuidAndMappedRates(): void
    {
        [$orderRepository, , , $processor] = $this->processor();
        $orderRepository->method('findOneBy')->with(['tokenValue' => 'cart-token'])
            ->willReturn($this->orderWithShipment(5, 'enviatodo_estafeta'));

        $payload = $this->quotePayload();
        $result = $processor->process(
            new LiveRatesQuoteRequest($payload['origin'], $payload['destination'], $payload['package']),
            new \ApiPlatform\Metadata\Post(),
            $this->uriVariables(),
        );

        $this->assertSame('uuid-1', $result->transactionUuid);
        $this->assertCount(1, $result->rates);
        $this->assertSame(23200, $result->rates[0]->amountMinor);
    }

    public function testProcessRejectsUnknownOrdersAndShipments(): void
    {
        [$orderRepository, , , $processor] = $this->processor();
        $orderRepository->method('findOneBy')->willReturn(null);

        $payload = $this->quotePayload();

        $this->expectException(NotFoundHttpException::class);
        $processor->process(
            new LiveRatesQuoteRequest($payload['origin'], $payload['destination'], $payload['package']),
            new \ApiPlatform\Metadata\Post(),
            $this->uriVariables(),
        );
    }

    public function testProcessRejectsMethodsOutsideTheMap(): void
    {
        [$orderRepository, , , $processor] = $this->processor();
        $orderRepository->method('findOneBy')->willReturn($this->orderWithShipment(5, 'flat_rate'));

        $payload = $this->quotePayload();

        $this->expectException(UnprocessableEntityHttpException::class);
        $processor->process(
            new LiveRatesQuoteRequest($payload['origin'], $payload['destination'], $payload['package']),
            new \ApiPlatform\Metadata\Post(),
            $this->uriVariables(),
        );
    }

    public function testProcessRejectsNonRequestPayloads(): void
    {
        [, , , $processor] = $this->processor();

        $this->expectException(\InvalidArgumentException::class);
        $processor->process(new \stdClass(), new \ApiPlatform\Metadata\Post(), $this->uriVariables());
    }

    public function testProcessRejectsChannelMismatch(): void
    {
        [$orderRepository, , , $processor] = $this->processor();
        $orderRepository->method('findOneBy')->willReturn($this->orderWithShipment(5, 'enviatodo_estafeta', 'OTHER'));

        $payload = $this->quotePayload();

        $this->expectException(NotFoundHttpException::class);
        $processor->process(
            new LiveRatesQuoteRequest($payload['origin'], $payload['destination'], $payload['package']),
            new \ApiPlatform\Metadata\Post(),
            $this->uriVariables(),
        );
    }

    public function testProcessRejectsNonCartOrders(): void
    {
        [$orderRepository, , , $processor] = $this->processor();
        $orderRepository->method('findOneBy')->willReturn($this->orderWithShipment(5, 'enviatodo_estafeta', 'WEB', 'fulfilled'));

        $payload = $this->quotePayload();

        $this->expectException(UnprocessableEntityHttpException::class);
        $processor->process(
            new LiveRatesQuoteRequest($payload['origin'], $payload['destination'], $payload['package']),
            new \ApiPlatform\Metadata\Post(),
            $this->uriVariables(),
        );
    }

    public function testProcessMapsUpstreamFailuresToBadGateway(): void
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('findOneBy')->willReturn($this->orderWithShipment(5, 'enviatodo_estafeta'));
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('all')->willThrowException(new EnviatodoException('envelope error'));
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB');
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $processor = new LiveRatesProcessor(
            $orderRepository,
            $channelContext,
            new EnviatodoCalculator($quoteApi, $this->createMock(ZipCodeApi::class)),
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );

        $payload = $this->quotePayload();

        try {
            $processor->process(
                new LiveRatesQuoteRequest($payload['origin'], $payload['destination'], $payload['package']),
                new \ApiPlatform\Metadata\Post(),
                $this->uriVariables(),
            );
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }

    public function testProcessMapsInvalidPayloadsToUnprocessableEntity(): void
    {
        [$orderRepository, , , $processor] = $this->processor();
        $orderRepository->method('findOneBy')->willReturn($this->orderWithShipment(5, 'enviatodo_estafeta'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $processor->process(
            new LiveRatesQuoteRequest([], [], []),
            new \ApiPlatform\Metadata\Post(),
            $this->uriVariables(),
        );
    }
}
