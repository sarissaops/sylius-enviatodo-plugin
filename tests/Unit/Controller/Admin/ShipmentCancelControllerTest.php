<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Admin;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\OrderApi;
use SarissaOps\Enviatodo\Model\Order\CancelResponse;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Admin\ShipmentCancelController;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\ShipmentCanceller;
use Psr\Log\NullLogger;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ShipmentCancelControllerTest extends TestCase
{
    private function controller(OrderApi $orderApi, bool $csrfValid, bool $admin = true): ShipmentCancelController
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $record = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $record->complete('TRK1', 23200);
        $repository->method('findOneByShipmentId')->willReturnCallback(
            static fn (int $id): ?EnviatodoShipment => 5 === $id ? $record : null,
        );
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->createMock(\Doctrine\ORM\EntityManagerInterface::class));
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with('ROLE_ADMINISTRATION_ACCESS')->willReturn($admin);

        return new ShipmentCancelController(
            new ShipmentCanceller($orderApi, new NullLogger()),
            $repository,
            $registry,
            $csrf,
            $authorizationChecker,
        );
    }

    private function orderApi(): OrderApi
    {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->method('cancel')->willReturn(CancelResponse::create([]));

        return $orderApi;
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return ['reason_value' => '7', 'reason_text' => 'QA cancel'];
    }

    public function testCancelActionCancelsAndMarksTheRecord(): void
    {
        $request = Request::create('/admin/enviatodo/shipments/5/cancel', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $response = $this->controller($this->orderApi(), true)->cancelAction($request, 5);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['cancelled']);
        $this->assertSame('TRK1', $data['tracking_id']);
    }

    public function testCancelActionReturns404WithoutALabel(): void
    {
        $request = Request::create('/admin/enviatodo/shipments/9/cancel', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $this->assertSame(404, $this->controller($this->orderApi(), true)->cancelAction($request, 9)->getStatusCode());
    }

    public function testCancelActionRejectsInvalidCsrfTokensAndPayloads(): void
    {
        $valid = Request::create('/admin/enviatodo/shipments/5/cancel', 'POST', [], [], [], [], (string) json_encode($this->body()));
        $badBody = Request::create('/admin/enviatodo/shipments/5/cancel', 'POST', [], [], [], [], (string) json_encode([]));

        $this->assertSame(403, $this->controller($this->orderApi(), false)->cancelAction($valid, 5)->getStatusCode());
        $this->assertSame(400, $this->controller($this->orderApi(), true)->cancelAction($badBody, 5)->getStatusCode());
    }

    public function testCancelActionForbidsNonAdministrators(): void
    {
        $request = Request::create('/admin/enviatodo/shipments/5/cancel', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $this->assertSame(403, $this->controller($this->orderApi(), true, false)->cancelAction($request, 5)->getStatusCode());
    }

    public function testCancelActionSkipsAlreadyCancelledRecords(): void
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $record = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $record->complete('TRK1', 23200);
        $record->markCancelled();
        $repository->method('findOneByShipmentId')->willReturn($record);
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->expects($this->never())->method('cancel');
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $controller = new ShipmentCancelController(
            new ShipmentCanceller($orderApi, new NullLogger()),
            $repository,
            $this->createMock(ManagerRegistry::class),
            $csrf,
            $authorizationChecker,
        );
        $request = Request::create('/admin/enviatodo/shipments/5/cancel', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $response = $controller->cancelAction($request, 5);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['already_cancelled']);
    }

    public function testCancelActionMapsProviderFailuresToBadGateway(): void
    {
        $orderApi = $this->createMock(OrderApi::class);
        $orderApi->method('cancel')->willThrowException(\SarissaOps\Enviatodo\Exception\HttpServerException::serverError());
        $request = Request::create('/admin/enviatodo/shipments/5/cancel', 'POST', [], [], [], [], (string) json_encode($this->body()));

        $this->assertSame(502, $this->controller($orderApi, true)->cancelAction($request, 5)->getStatusCode());
    }
}
