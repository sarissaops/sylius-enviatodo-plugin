<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\PackageApi;
use SarissaOps\Enviatodo\Model\Package\Package;
use SarissaOps\Enviatodo\Model\Package\SavedPackage;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Admin\PackageController;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoPackageCatalog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PackageControllerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function packageData(): array
    {
        return ['id' => '5', 'name' => 'Box', 'default_pkg' => '0', 'bill_weight' => '1'];
    }

    private function controller(PackageApi $packageApi, bool $csrfValid): PackageController
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        return new PackageController(new EnviatodoPackageCatalog($packageApi, new NullLogger()), $csrf, $this->authorizationChecker());
    }

    private function authorizationChecker(): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with('ROLE_ADMINISTRATION_ACCESS')->willReturn(true);

        return $authorizationChecker;
    }

    private function packageApi(): PackageApi
    {
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->method('all')->willReturn([Package::create($this->packageData())]);
        $packageApi->method('show')->willReturn([Package::create($this->packageData())]);
        $packageApi->method('save')->willReturn(SavedPackage::create(['package' => $this->packageData()]));

        return $packageApi;
    }

    public function testListAndShowActions(): void
    {
        $controller = $this->controller($this->packageApi(), true);

        $list = $controller->listAction(Request::create('/admin/enviatodo/packages', 'GET'));
        $this->assertSame(200, $list->getStatusCode());
        /** @var array<string, mixed> $listData */
        $listData = json_decode((string) $list->getContent(), true);
        /** @var list<array<string, mixed>> $packages */
        $packages = $listData['packages'];
        $this->assertCount(1, $packages);
        $this->assertSame('Box', $packages[0]['name']);

        $show = $controller->showAction(Request::create('/admin/enviatodo/packages/5', 'GET'), 5);
        $this->assertSame(200, $show->getStatusCode());
    }

    public function testShowActionReturns404WhenAbsent(): void
    {
        $packageApi = $this->createMock(PackageApi::class);
        $packageApi->method('show')->willReturn([]);

        $response = $this->controller($packageApi, true)->showAction(Request::create('/x', 'GET'), 404);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSaveAndDeleteActions(): void
    {
        $controller = $this->controller($this->packageApi(), true);

        $save = $controller->saveAction(Request::create('/admin/enviatodo/packages', 'POST', [], [], [], [], (string) json_encode($this->packageData())));
        $this->assertSame(200, $save->getStatusCode());

        $delete = $controller->deleteAction(Request::create('/admin/enviatodo/packages/5/delete', 'POST'), 5);
        $this->assertSame(200, $delete->getStatusCode());
    }

    public function testMutatingActionsRejectInvalidCsrfTokens(): void
    {
        $controller = $this->controller($this->packageApi(), false);
        $request = Request::create('/admin/enviatodo/packages', 'POST', [], [], [], [], (string) json_encode($this->packageData()));

        $this->assertSame(403, $controller->saveAction($request)->getStatusCode());
        $this->assertSame(403, $controller->deleteAction(Request::create('/admin/enviatodo/packages/5/delete', 'POST'), 5)->getStatusCode());
    }
}
