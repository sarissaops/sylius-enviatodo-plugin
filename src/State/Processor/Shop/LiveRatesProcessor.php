<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\State\Processor\Shop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRate;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRatesQuoteRequest;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRatesResult;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Guest-accessible live rates for one checkout shipment. The order token in
 * the URI scopes the lookup; the shipment method resolves the provider pair.
 *
 * @implements ProcessorInterface<LiveRatesQuoteRequest, LiveRatesResult>
 */
final class LiveRatesProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var OrderRepositoryInterface<OrderInterface> */
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EnviatodoCalculator $calculator,
        private readonly ProviderMap $providerMap,
    ) {
    }

    /**
     * @param array<string, string> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LiveRatesResult
    {
        if (!$data instanceof LiveRatesQuoteRequest) {
            throw new \InvalidArgumentException('Live rates require a LiveRatesQuoteRequest payload.');
        }

        $tokenValue = $uriVariables['tokenValue'] ?? null;
        $shipmentId = $uriVariables['shipmentId'] ?? null;
        if (!\is_string($tokenValue) || '' === $tokenValue || !\is_string($shipmentId) || '' === $shipmentId) {
            throw new NotFoundHttpException('Order or shipment not found.');
        }

        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        if (null === $order) {
            throw new NotFoundHttpException('Order not found.');
        }

        $shipment = null;
        foreach ($order->getShipments() as $candidate) {
            if ($candidate->getId() === (int) $shipmentId) {
                $shipment = $candidate;

                break;
            }
        }
        if (null === $shipment) {
            throw new NotFoundHttpException('Shipment not found.');
        }

        $code = $shipment->getMethod()?->getCode();
        $mapping = null !== $code ? $this->providerMap->getProviderService($code) : null;
        if (null === $mapping) {
            throw new UnprocessableEntityHttpException('Shipping method is not backed by Enviatodo.');
        }

        $quoted = $this->calculator->rates(
            QuotePayload::create(
                QuotePayload::normalize($data->origin),
                QuotePayload::normalize($data->destination),
                QuotePayload::normalize($data->package),
                $data->quantity,
            ),
        );

        $rates = [];
        foreach ($quoted->getRates() as $rate) {
            if ($rate->getProviderId() === $mapping['provider_id'] &&
                $rate->getProviderServiceId() === $mapping['provider_service_id']
            ) {
                $rates[] = LiveRate::fromRateResult($rate);
            }
        }

        return new LiveRatesResult($quoted->getTransactionUuid(), $rates);
    }
}
