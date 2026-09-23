<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SarissaOps\Enviatodo\Api\AddressApi;
use SarissaOps\Enviatodo\Model\Address\Address;
use SarissaOps\Enviatodo\Model\Address\SavedAddress;
use SarissaOps\SyliusEnviatodoPlugin\Controller\Admin\AddressController;
use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoAddressBook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AddressControllerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function addressData(): array
    {
        return ['id' => '12', 'address_type_id' => '1', 'full_name' => 'HQ', 'company' => 'Acme', 'zip_code' => '64000'];
    }

    private function controller(AddressApi $addressApi, bool $csrfValid): AddressController
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        return new AddressController(new EnviatodoAddressBook($addressApi, new NullLogger()), $csrf);
    }

    private function addressApi(): AddressApi
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->method('all')->willReturn([Address::create($this->addressData())]);
        $addressApi->method('show')->willReturn([Address::create($this->addressData())]);
        $addressApi->method('save')->willReturn(SavedAddress::create(['address' => $this->addressData()]));

        return $addressApi;
    }

    public function testListAndShowActions(): void
    {
        $controller = $this->controller($this->addressApi(), true);

        $list = $controller->listAction(Request::create('/admin/enviatodo/addresses', 'GET'));
        $this->assertSame(200, $list->getStatusCode());
        /** @var array<string, mixed> $listData */
        $listData = json_decode((string) $list->getContent(), true);
        /** @var list<array<string, mixed>> $addresses */
        $addresses = $listData['addresses'];
        $this->assertCount(1, $addresses);
        $this->assertSame('Acme', $addresses[0]['company']);

        $show = $controller->showAction(Request::create('/admin/enviatodo/addresses/12', 'GET'), 12);
        $this->assertSame(200, $show->getStatusCode());
    }

    public function testShowActionReturns404WhenAbsent(): void
    {
        $addressApi = $this->createMock(AddressApi::class);
        $addressApi->method('show')->willReturn([]);

        $response = $this->controller($addressApi, true)->showAction(Request::create('/x', 'GET'), 404);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSaveAndDeleteActions(): void
    {
        $controller = $this->controller($this->addressApi(), true);

        $save = $controller->saveAction(Request::create('/admin/enviatodo/addresses', 'POST', [], [], [], [], (string) json_encode($this->addressData())));
        $this->assertSame(200, $save->getStatusCode());

        $delete = $controller->deleteAction(Request::create('/admin/enviatodo/addresses/12/delete', 'POST'), 12);
        $this->assertSame(200, $delete->getStatusCode());
    }

    public function testMutatingActionsRejectInvalidCsrfTokens(): void
    {
        $controller = $this->controller($this->addressApi(), false);
        $request = Request::create('/admin/enviatodo/addresses', 'POST', [], [], [], [], (string) json_encode($this->addressData()));

        $this->assertSame(403, $controller->saveAction($request)->getStatusCode());
    }
}
