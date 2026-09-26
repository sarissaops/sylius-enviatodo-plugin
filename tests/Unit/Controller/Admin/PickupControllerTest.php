<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\PickupApi;
use SarissaOps\Enviatodo\Model\Pickup\PickupOrder;
use SarissaOps\Enviatodo\Model\Pickup\PickupResponse;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Admin\PickupController;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPickupScheduler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PickupControllerTest extends TestCase
{
    private function controller(PickupApi $pickupApi, bool $csrfValid, bool $admin = true): PickupController
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with('ROLE_ADMINISTRATION_ACCESS')->willReturn($admin);
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('admin-1');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new PickupController(new EnviatodoPickupScheduler($pickupApi, new NullLogger(), false), $csrf, $authorizationChecker, $security);
    }

    private function pickupData(): PickupApi
    {
        return $this->pickupApi;
    }

    /** @var PickupApi */
    private PickupApi $pickupApi;

    protected function setUp(): void
    {
        parent::setUp();

        $pickupApi = $this->createMock(PickupApi::class);
        $pickupApi->method('ordersForPickup')->willReturn([
            PickupOrder::create(['id' => '1', 'tracking_id' => 'TRK1', 'provider_name' => 'FEDEX', 'provider_id' => '1', 'service_name' => 'Terrestre', 'origin' => 'Monterrey', 'created_at' => '2026-09-22']),
        ]);
        $pickupApi->method('create')->willReturn(PickupResponse::create([]));
        $pickupApi->method('cancel')->willReturn(PickupResponse::create([]));

        $this->pickupApi = $pickupApi;
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return [
            'who_delivers' => 'Juan Perez',
            'contact_phone' => '8180000000',
            'contact_email' => 'dock@example.com',
            'data' => [
                ['trx_id' => '426855', 'origin_address' => 'Zarco 940, Monterrey'],
            ],
            'pickup_date' => '2026-09-23 06:13 pm',
            'pickup_place' => 2,
            'pickup_place_data' => 'OFICINA',
            'origin' => 'Zarco 940, Monterrey',
            'provider_id' => '1',
            'description_reference' => 'daily run',
        ];
    }

    public function testQueueActionListsEligibleOrders(): void
    {
        $request = Request::create('/admin/enviatodo/pickups/queue?providers=1', 'GET');

        $response = $this->controller($this->pickupData(), true)->queueAction($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        /** @var list<array<string, mixed>> $orders */
        $orders = $data['orders'];
        $this->assertCount(1, $orders);
        $this->assertSame('TRK1', $orders[0]['tracking_id']);
    }

    public function testCreateActionSchedulesThePickup(): void
    {
        $request = Request::create('/admin/enviatodo/pickups', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $response = $this->controller($this->pickupData(), true)->createAction($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['created']);
    }

    public function testCancelActionCarriesAudit(): void
    {
        $request = Request::create('/admin/enviatodo/pickups/29114/cancel', 'POST', [], [], [], [], (string) json_encode(['description' => 'no longer needed', 'user_id' => '7']));

        $response = $this->controller($this->pickupData(), true)->cancelAction($request, 29114);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testActionsRejectInvalidCsrfTokensAndPayloads(): void
    {
        $request = Request::create('/admin/enviatodo/pickups/29114/cancel', 'POST', [], [], [], [], (string) json_encode([]));

        $this->assertSame(403, $this->controller($this->pickupData(), false)->cancelAction($request, 29114)->getStatusCode());
        $this->assertSame(400, $this->controller($this->pickupData(), true)->cancelAction($request, 29114)->getStatusCode());
    }

    public function testCreateActionRejectsInvalidCsrfTokens(): void
    {
        $request = Request::create('/admin/enviatodo/pickups', 'POST', [], [], [], [], (string) json_encode([]));

        $this->assertSame(403, $this->controller($this->pickupData(), false)->createAction($request)->getStatusCode());
    }

    public function testActionsForbidNonAdministrators(): void
    {
        $request = Request::create('/admin/enviatodo/pickups/queue?providers=9', 'GET');

        $this->assertSame(403, $this->controller($this->pickupData(), true, false)->queueAction($request)->getStatusCode());
    }
}
