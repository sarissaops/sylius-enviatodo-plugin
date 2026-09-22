<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Shop;

use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\QuoteApi;
use SarissaOps\Enviatodo\Model\Quote\Quote;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\Calculator\EnviatodoCalculator;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Shop\QuoteController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class QuoteControllerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function body(): array
    {
        return [
            'origin' => ['zip_code' => '64000'],
            'destination' => ['zip_code' => '06600'],
            'package' => [
                'product_type' => '01010101',
                'unit_type' => 'X1A',
                'package_content' => 'books',
                'amount_pkg' => '200',
                'height' => 10,
                'width' => 10,
                'length' => 10,
                'weight' => 1,
                'real_weight' => '1.00',
                'volumetric_weight' => '1',
                'bill_weight' => '1',
                'default_pkg' => '0',
            ],
        ];
    }

    private function controller(QuoteApi $quoteApi, bool $csrfValid): QuoteController
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        return new QuoteController(new EnviatodoCalculator($quoteApi), $csrf);
    }

    private function quoteApi(): QuoteApi
    {
        $quoteApi = $this->createMock(QuoteApi::class);
        $quoteApi->method('all')->willReturn(Quote::create([
            'transaction' => ['type' => 'order', 'timestamp' => '2026-01-01', 'uuid' => 'uuid-1'],
            'packages' => ['quantity' => 1],
            'rates' => [
                [
                    'provider_id' => '9',
                    'provider_service_id' => '11',
                    'provider_name' => 'ESTAFETA',
                    'service_name' => 'Aéreo',
                    'status' => true,
                    'charges' => [['type' => 'base', 'sub_total' => '200.00', 'tax' => '32.00', 'total' => '232.00']],
                    'detail_charges' => [['currency' => 'MXN']],
                ],
            ],
        ]));

        return $quoteApi;
    }

    public function testQuoteActionReturnsUuidAndRates(): void
    {
        $request = Request::create('/enviatodo/ajax/quote', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $response = $this->controller($this->quoteApi(), true)->quoteAction($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('uuid-1', $data['uuid']);
        /** @var list<array<string, mixed>> $rates */
        $rates = $data['rates'];
        $this->assertCount(1, $rates);
        $this->assertSame(23200, $rates[0]['amount_minor']);
    }

    public function testQuoteActionRejectsInvalidCsrfTokens(): void
    {
        $request = Request::create('/enviatodo/ajax/quote', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $response = $this->controller($this->quoteApi(), false)->quoteAction($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testQuoteActionRejectsMalformedPayloads(): void
    {
        $badJson = Request::create('/enviatodo/ajax/quote', 'POST', [], [], [], [], '{nope');
        $missingKeys = Request::create('/enviatodo/ajax/quote', 'POST', [], [], [], [], (string) json_encode(['origin' => []]));

        $this->assertSame(400, $this->controller($this->quoteApi(), true)->quoteAction($badJson)->getStatusCode());
        $this->assertSame(400, $this->controller($this->quoteApi(), true)->quoteAction($missingKeys)->getStatusCode());
    }
}
