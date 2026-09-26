<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\State\Processor\Shop;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Psr\Log\LoggerInterface;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRate;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRatesQuoteRequest;
use SarissaOps\SyliusEnviatodoPlugin\ApiResource\LiveRatesResult;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Guest-accessible live rates for one checkout shipment. The order token in
 * the URI scopes the lookup to the request channel's active checkout; upstream
 * failures map to API-problem statuses instead of HTML 500s.
 *
 * @implements ProcessorInterface<LiveRatesQuoteRequest, LiveRatesResult>
 */
final class LiveRatesProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var OrderRepositoryInterface<OrderInterface> */
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly EnviatodoCalculator $calculator,
        private readonly ProviderMap $providerMap,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables API Platform injects resolved entities for fromClass variables.
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LiveRatesResult
    {
        if (!$data instanceof LiveRatesQuoteRequest) {
            throw new \InvalidArgumentException('Live rates require a LiveRatesQuoteRequest payload.');
        }
        $this->logger->debug('Enviatodo live rates request.', [
            'token_variable' => get_debug_type($uriVariables['tokenValue'] ?? null),
            'shipment_variable' => get_debug_type($uriVariables['shipmentId'] ?? null),
        ]);

        $order = $uriVariables['tokenValue'] ?? null;
        if (\is_string($order)) {
            $order = '' !== $order ? $this->orderRepository->findOneBy(['tokenValue' => $order]) : null;
        }
        if (!$order instanceof OrderInterface) {
            throw new NotFoundHttpException('Order not found.');
        }
        try {
            $requestChannelCode = $this->channelContext->getChannel()->getCode();
        } catch (\Exception $exception) {
            throw new NotFoundHttpException('Order not found.', $exception);
        }
        if ($order->getChannel()?->getCode() !== $requestChannelCode) {
            throw new NotFoundHttpException('Order not found.');
        }
        if (!\in_array($order->getCheckoutState(), [OrderCheckoutStates::STATE_CART, OrderCheckoutStates::STATE_ADDRESSED, OrderCheckoutStates::STATE_SHIPPING_SELECTED], true)) {
            throw new UnprocessableEntityHttpException('Live rates require an active checkout.');
        }

        $shipment = $uriVariables['shipmentId'] ?? null;
        if (!\is_object($shipment)) {
            $shipment = null;
            $rawId = $uriVariables['shipmentId'] ?? null;
            $wantedId = \is_int($rawId) ? $rawId : (\is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0);
            foreach ($order->getShipments() as $candidate) {
                if ($candidate->getId() === $wantedId) {
                    $shipment = $candidate;

                    break;
                }
            }
        }
        if (!$shipment instanceof ShipmentInterface || $shipment->getOrder() === null || $shipment->getOrder()->getId() !== $order->getId()) {
            throw new NotFoundHttpException('Shipment not found.');
        }

        $code = $shipment->getMethod()?->getCode();
        $mapping = null !== $code ? $this->providerMap->getProviderService($code) : null;
        if (null === $mapping) {
            throw new UnprocessableEntityHttpException('Shipping method is not backed by Enviatodo.');
        }

        try {
            $quoted = $this->calculator->rates(
                QuotePayload::create(
                    QuotePayload::normalize($data->origin),
                    QuotePayload::normalize($data->destination),
                    QuotePayload::normalize($data->package),
                    $data->quantity,
                    $data->shippingType,
                ),
            );
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException('Quote payload is invalid.', $exception);
        } catch (HttpClientException $exception) {
            // Deterministic rejections never succeed on retry: bad input,
            // exhausted balance, and rate limiting are client-side 422s.
            if (\in_array($exception->getResponseCode(), [400, 402, 429], true)) {
                throw new UnprocessableEntityHttpException('Quote rejected by the rate provider.', $exception);
            }

            throw new HttpException(502, 'Rate provider unavailable.', $exception);
        } catch (EnviatodoException | HttpServerException | \RuntimeException $exception) {
            $this->logger->error('Enviatodo live rates unavailable.', [
                'channel' => $order->getChannel()?->getCode(),
                'shipment' => $shipment->getId(),
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(502, 'Rate provider unavailable.', $exception);
        }

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
