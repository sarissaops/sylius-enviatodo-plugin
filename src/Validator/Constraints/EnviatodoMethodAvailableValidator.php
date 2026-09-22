<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Validator\Constraints;

use SarissaOps\Enviatodo\Api\ZipCodeApi;
use SarissaOps\Enviatodo\Exception\EnviatodoException;
use SarissaOps\Enviatodo\Exception\HttpClientException;
use SarissaOps\Enviatodo\Exception\HttpServerException;
use SarissaOps\Enviatodo\Model\ZipCode\ZipCode;
use SarissaOps\SyliusEnviatodoPlugin\Component\Shipping\ProviderMap;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EnviatodoMethodAvailableValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ZipCodeApi $zipCodeApi,
        private readonly ProviderMap $providerMap,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EnviatodoMethodAvailable) {
            throw new UnexpectedTypeException($constraint, EnviatodoMethodAvailable::class);
        }
        if (!$value instanceof OrderInterface) {
            return;
        }

        foreach ($value->getShipments() as $shipment) {
            $code = $shipment->getMethod()?->getCode();
            if (null === $code || !$this->providerMap->isBackedByEnviatodo($code)) {
                continue;
            }

            $postcode = $value->getShippingAddress()?->getPostcode();
            if (null === $postcode || '' === $postcode) {
                $this->context->buildViolation($constraint->message)->addViolation();

                continue;
            }

            try {
                $zip = $this->zipCodeApi->show($postcode);
            } catch (EnviatodoException | HttpClientException | HttpServerException) {
                $this->context->buildViolation($constraint->message)->addViolation();

                continue;
            }

            if (!$zip instanceof ZipCode || [] === $zip->getItems()) {
                $this->context->buildViolation($constraint->message)->addViolation();
            }
        }
    }
}
