<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Unit\Validator;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use SarissaOps\SyliusEnviatodoPlugin\Validator\Constraints\EnviatodoMethodAvailable;
use SarissaOps\SyliusEnviatodoPlugin\Validator\Constraints\EnviatodoMethodAvailableValidator;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class EnviatodoMethodAvailableValidatorTest extends TestCase
{
    private function order(string $methodCode, ?string $postcode): OrderInterface
    {
        $method = $this->createMock(ShippingMethodInterface::class);
        $method->method('getCode')->willReturn($methodCode);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($method);

        $address = null;
        if (null !== $postcode) {
            $address = $this->createMock(AddressInterface::class);
            $address->method('getPostcode')->willReturn($postcode);
        }

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $order->method('getShippingAddress')->willReturn($address);

        return $order;
    }

    private function validator(ZipCodeApi $zipCodeApi): EnviatodoMethodAvailableValidator
    {
        $validator = new EnviatodoMethodAvailableValidator(
            $zipCodeApi,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');
        $validator->initialize($context);

        return $validator;
    }

    private function zipWithItems(): ZipCode
    {
        return ZipCode::create(['component' => 'zip', 'type' => 'list', 'items' => [['zip_code' => '64000']]]);
    }

    public function testSkipsOrdersWithoutShipments(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection([]));

        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->never())->method('show');

        $this->validator($zipCodeApi)->validate($order, new EnviatodoMethodAvailable());

        $this->addToAssertionCount(1);
    }

    public function testSkipsMethodsOutsideTheMap(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->never())->method('show');

        $this->validator($zipCodeApi)->validate($this->order('flat_rate', '64000'), new EnviatodoMethodAvailable());

        $this->addToAssertionCount(1);
    }

    public function testAcceptsCoveredZipCodes(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->expects($this->once())->method('show')->with('64000')->willReturn($this->zipWithItems());

        $this->validator($zipCodeApi)->validate($this->order('enviatodo_estafeta', '64000'), new EnviatodoMethodAvailable());

        $this->addToAssertionCount(1);
    }

    public function testRejectsUncoveredZipCodes(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willReturn(ZipCode::create(['component' => 'zip', 'type' => 'list', 'items' => []]));

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('addViolation');
        $context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $validator = new EnviatodoMethodAvailableValidator(
            $zipCodeApi,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );
        $validator->initialize($context);
        $validator->validate($this->order('enviatodo_estafeta', '00000'), new EnviatodoMethodAvailable());
    }

    public function testRejectsApiFailuresAndMissingPostcodes(): void
    {
        $zipCodeApi = $this->createMock(ZipCodeApi::class);
        $zipCodeApi->method('show')->willThrowException(new EnviatodoException('boom'));

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->exactly(2))->method('addViolation');
        $context->expects($this->exactly(2))->method('buildViolation')->willReturn($builder);

        $validator = new EnviatodoMethodAvailableValidator(
            $zipCodeApi,
            new ProviderMap(['enviatodo_estafeta' => ['provider_id' => '9', 'provider_service_id' => '11']]),
        );
        $validator->initialize($context);
        $constraint = new EnviatodoMethodAvailable();
        $validator->validate($this->order('enviatodo_estafeta', '64000'), $constraint);
        $validator->validate($this->order('enviatodo_estafeta', null), $constraint);
    }
}
