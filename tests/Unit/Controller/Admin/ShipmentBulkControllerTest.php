<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\GuideApi;
use SarissaOps\Enviatodo\Model\Guide\GuideBinaries;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Admin\ShipmentBulkController;
use SarissaOps\SyliusEnviatodoPlugin\Entity\EnviatodoShipment;
use SarissaOps\SyliusEnviatodoPlugin\Repository\EnviatodoShipmentRepositoryInterface;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoLabelService;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ShipmentBulkControllerTest extends TestCase
{
    private function record(string $guideId): EnviatodoShipment
    {
        $record = new EnviatodoShipment($this->createMock(ShipmentInterface::class), 'uuid-1', '9', '11');
        $record->complete('TRK1', 23200);
        $record->setGuideId($guideId);

        return $record;
    }

    /** @return array{EnviatodoShipmentRepositoryInterface, ShipmentBulkController} */
    private function controller(bool $csrfValid, bool $admin = true): array
    {
        $repository = $this->createMock(EnviatodoShipmentRepositoryInterface::class);
        $guideApi = $this->createMock(GuideApi::class);
        $guideApi->method('binaries')->willReturn(GuideBinaries::create([
            'files' => [['guide_id' => 7, 'name' => 'label.pdf', 'binary' => 'Ym9keQ==', 'mime' => 'application/pdf']],
            'invalid_files' => [],
        ]));
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with('ROLE_ADMINISTRATION_ACCESS')->willReturn($admin);

        return [$repository, new ShipmentBulkController(new EnviatodoLabelService($guideApi), $repository, $csrf, new NullLogger(), $authorizationChecker)];
    }

    public function testBulkLabelsSkipsShipmentsWithoutGuideIds(): void
    {
        [$repository, $controller] = $this->controller(true);
        $record = $this->record('7');
        $repository->method('findOneByShipmentId')->willReturnCallback(
            static fn (int $id): ?EnviatodoShipment => 1 === $id ? $record : null,
        );

        $request = Request::create('/admin/enviatodo/shipments/bulk-labels', 'POST', [], [], [], [], (string) json_encode(['shipment_ids' => [1, 2]]));
        $response = $controller->bulkLabelsAction($request);

        $this->assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        /** @var list<array<string, mixed>> $files */
        $files = $data['files'];
        $this->assertCount(1, $files);
        $this->assertSame(7, $files[0]['guide_id']);
    }

    public function testBulkLabelsRejectsInvalidCsrfTokensAndPayloads(): void
    {
        [, $controller] = $this->controller(false);
        $request = Request::create('/admin/enviatodo/shipments/bulk-labels', 'POST', [], [], [], [], (string) json_encode(['shipment_ids' => [1]]));

        $this->assertSame(403, $controller->bulkLabelsAction($request)->getStatusCode());

        [, $validController] = $this->controller(true);
        $badJson = Request::create('/admin/enviatodo/shipments/bulk-labels', 'POST', [], [], [], [], '{nope');

        $this->assertSame(400, $validController->bulkLabelsAction($badJson)->getStatusCode());
    }

    public function testBulkLabelsForbidsNonAdministrators(): void
    {
        [, $controller] = $this->controller(true, false);
        $request = Request::create('/admin/enviatodo/shipments/bulk-labels', 'POST', [], [], [], [], (string) json_encode(['shipment_ids' => [1]]));

        $this->assertSame(403, $controller->bulkLabelsAction($request)->getStatusCode());
    }
}
