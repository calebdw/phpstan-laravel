<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Reflection;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

final class SimpleParameterReflection implements ParameterReflection
{
    private PassedByReference $passedByReference;

    public function __construct(
        private string $name,
        private Type $type,
        private bool $optional = false,
        PassedByReference|null $passedByReference = null,
        private bool $variadic = false,
        private Type|null $defaultValue = null,
    ) {
        $this->passedByReference = $passedByReference ?? PassedByReference::createNo();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function passedByReference(): PassedByReference
    {
        return $this->passedByReference;
    }

    public function isVariadic(): bool
    {
        return $this->variadic;
    }

    public function getDefaultValue(): Type|null
    {
        return $this->defaultValue;
    }
}
