<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Shop;

use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Guest checkout rate preview. Scoped to a live cart in the request
 * channel: the cart token binds quota burn to a real checkout session
 * instead of serving anonymous arbitrary quotes.
 */
final class QuoteController
{
    public const CSRF_TOKEN_ID = 'enviatodo_quote';

    public function __construct(
        private readonly EnviatodoCalculator $calculator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        /** @var OrderRepositoryInterface<OrderInterface> */
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function quoteAction(Request $request): JsonResponse
    {
        $guard = CsrfProtection::validate($request, $this->csrfTokenManager, self::CSRF_TOKEN_ID);
        if (null !== $guard) {
            return $guard;
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $cartToken = $body['cart_token'] ?? null;
        if (!\is_string($cartToken) || '' === $cartToken) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $order = $this->orderRepository->findOneBy(['tokenValue' => $cartToken]);
        if (!$order instanceof OrderInterface ||
            $order->getChannel()?->getCode() !== $this->channelContext->getChannel()->getCode() ||
            $order->getCheckoutState() !== OrderCheckoutStates::STATE_CART
        ) {
            return new JsonResponse(['error' => 'cart_not_found'], Response::HTTP_NOT_FOUND);
        }

        $origin = $body['origin'] ?? null;
        $destination = $body['destination'] ?? null;
        $package = $body['package'] ?? null;
        if (!\is_array($origin) || !\is_array($destination) || !\is_array($package)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $quantity = $body['quantity'] ?? 1;
        if (!\is_int($quantity)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }
        $shippingType = $body['shipping_type'] ?? '1';
        if (!\is_string($shippingType) || '' === $shippingType) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = QuotePayload::create(
                QuotePayload::normalize($origin),
                QuotePayload::normalize($destination),
                QuotePayload::normalize($package),
                $quantity,
                $shippingType,
            );
            $quoted = $this->calculator->rates($payload);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (EnviatodoException | HttpClientException | HttpServerException | \RuntimeException) {
            return new JsonResponse(['error' => 'quote_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        $rates = [];
        foreach ($quoted->getRates() as $rate) {
            $rates[] = [
                'provider_id' => $rate->getProviderId(),
                'provider_service_id' => $rate->getProviderServiceId(),
                'provider_name' => $rate->getProviderName(),
                'service_name' => $rate->getServiceName(),
                'amount_minor' => $rate->getAmountMinor(),
                'currency' => $rate->getCurrency(),
                'estimated_date' => $rate->getEstimatedDate(),
            ];
        }

        return new JsonResponse(['uuid' => $quoted->getTransactionUuid(), 'rates' => $rates]);
    }
}
