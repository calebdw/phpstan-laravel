<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\HigherOrderCollectionProxyMethodReflection;
use CalebDW\PhpstanLaravel\Support\HigherOrderCollectionProxyHelper;
use Illuminate\Database\Eloquent\Collection;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type;

use function count;

final class HigherOrderCollectionProxyExtension implements MethodsClassReflectionExtension
{
    public function __construct(private HigherOrderCollectionProxyHelper $higherOrderCollectionProxyHelper)
    {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $this->higherOrderCollectionProxyHelper->hasPropertyOrMethod($classReflection, $methodName, 'method');
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $activeTemplateTypeMap = $classReflection->getActiveTemplateTypeMap();

        /** @var Type\Constant\ConstantStringType $methodType */
        $methodType = $activeTemplateTypeMap->getType('T');

        /** @var Type\ObjectType $valueType */
        $valueType = $activeTemplateTypeMap->getType('TValue');

        /** @var Type\Type $collectionType */
        $collectionType = $activeTemplateTypeMap->getType('TCollection');

        $collectionClassName = count($collectionType->getObjectClassNames()) === 0
            ? Collection::class
            : $collectionType->getObjectClassNames()[0];

        $modelMethodReflection = $valueType->getMethod($methodName, new OutOfClassScope());

        $modelMethodReturnType = $modelMethodReflection->getVariants()[0]->getReturnType();

        $returnType = $this->higherOrderCollectionProxyHelper->determineReturnType(
            $methodType->getValue(),
            $valueType,
            $modelMethodReturnType,
            $collectionClassName,
            $collectionType->getIterableKeyType(),
        );

        return new HigherOrderCollectionProxyMethodReflection($classReflection, $methodName, $modelMethodReflection, $returnType);
    }
}
