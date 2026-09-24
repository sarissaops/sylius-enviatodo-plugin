<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\E2E;

use Symfony\Component\Panther\PantherTestCase;

/**
 * Real-browser smoke coverage: shop and admin entry points render.
 * Business flows stay in Behat (@ui / @javascript); this suite only proves
 * the application boots and serves both storefronts headlessly.
 */
final class ShopSmokeTest extends PantherTestCase
{
    public function testShopHomepageRenders(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/en_US/');

        $this->assertSelectorExists('body');
        $this->assertStringContainsString('/en_US', $client->getCurrentURL());
    }

    public function testAdminLoginRenders(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/admin/login');

        $this->assertSelectorExists('form');
        $this->assertStringContainsString('/admin/login', $client->getCurrentURL());
    }
}
