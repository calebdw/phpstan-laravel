<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Methods;

use CalebDW\PhpstanLaravel\Reflection\HigherOrderCollectionProxyMethodReflection;
use CalebDW\PhpstanLaravel\Support\HigherOrderCollectionProxyHelper;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

use function assert;

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
        $templates = $this->higherOrderCollectionProxyHelper->getProxyTemplates($classReflection);
        assert($templates !== null);

        $modelMethodReflection = $templates['value']->getMethod($methodName, new OutOfClassScope());
        $modelMethodReturnType = $modelMethodReflection->getVariants()[0]->getReturnType();

        $returnType = $this->higherOrderCollectionProxyHelper->determineReturnType(
            $templates['methods'],
            $templates['value'],
            $modelMethodReturnType,
            $templates['collection']->getObjectClassNames(),
            $templates['collection']->getIterableKeyType(),
        );

        return new HigherOrderCollectionProxyMethodReflection($classReflection, $methodName, $modelMethodReflection, $returnType);
    }
}
