<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
final class EnviatodoMethodAvailable extends Constraint
{
    public string $message = 'sylius_enviatodo.method_not_available';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return EnviatodoMethodAvailableValidator::class;
    }
}
