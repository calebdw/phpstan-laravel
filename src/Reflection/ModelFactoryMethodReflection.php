<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Reflection;

use Illuminate\Support\Str;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;

final class ModelFactoryMethodReflection implements MethodReflection
{
    /** @var ParametersAcceptor[] */
    private array $variants;

    public function __construct(private ClassReflection $declaringClass, private string $name)
    {
        $returnType     = new StaticType($this->declaringClass);
        $stateParameter = $this->declaringClass->getMethod('state', new OutOfClassScope())->getVariants()[0]->getParameters()[0];
        $countParameter = $this->declaringClass->getMethod('count', new OutOfClassScope())->getVariants()[0]->getParameters()[0];
        $this->variants = [new FunctionVariant(TemplateTypeMap::createEmpty(), null, [], false, $returnType)];

        if (Str::startsWith($this->name, 'for')) {
            $this->variants[] = new FunctionVariant(TemplateTypeMap::createEmpty(), null, [$stateParameter], false, $returnType);
        } else {
            $this->variants[] = new FunctionVariant(TemplateTypeMap::createEmpty(), null, [$countParameter], false, $returnType);
            $this->variants[] = new FunctionVariant(TemplateTypeMap::createEmpty(), null, [$stateParameter], false, $returnType);
            $this->variants[] = new FunctionVariant(TemplateTypeMap::createEmpty(), null, [$countParameter, $stateParameter], false, $returnType);
        }
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): string|null
    {
        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    /** @return ParametersAcceptor[] */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): string|null
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): Type|null
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createMaybe();
    }
}
