<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Controller\Shop;

use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\QuotePayload;
use SarissaOps\SyliusEnviatodoPlugin\Controller\CsrfProtection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class QuoteController
{
    public const CSRF_TOKEN_ID = 'enviatodo_quote';

    public function __construct(
        private readonly EnviatodoCalculator $calculator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
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

        try {
            $payload = QuotePayload::create(
                self::stringMap($origin),
                self::stringMap($destination),
                self::stringMap($package),
                $quantity,
            );
            $quoted = $this->calculator->rates($payload);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        } catch (EnviatodoException | HttpClientException | HttpServerException) {
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

    /**
     * @param array<mixed> $input
     *
     * @return array<string, mixed>
     */
    private static function stringMap(array $input): array
    {
        $mapped = [];
        foreach ($input as $key => $value) {
            if (\is_string($key)) {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }
}
