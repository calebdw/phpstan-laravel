<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ThrowTypes;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Promises\LazyPromise;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\DynamicStaticMethodThrowTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function in_array;

final class PendingRequestThrowTypeExtension implements DynamicMethodThrowTypeExtension, DynamicStaticMethodThrowTypeExtension
{
    private const array METHODS = ['get', 'head', 'post', 'patch', 'put', 'delete', 'query', 'send'];

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), self::METHODS, true)
            && $methodReflection->getDeclaringClass()->getName() === PendingRequest::class;
    }

    public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        return $this->throwType($scope->getType($methodCall));
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->isMethodSupported($methodReflection);
    }

    public function getThrowTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        return $this->throwType($scope->getType($methodCall));
    }

    private function throwType(Type $response): Type|null
    {
        // An async request returns a promise at once; failures only surface
        // when the promise settles. A synchronous one rethrows whatever its
        // retry, throw and middleware callbacks throw, so every verb shares
        // the @throws \Exception that Laravel puts on send().
        // phpcs:ignore SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly
        return (new ObjectType(LazyPromise::class))->isSuperTypeOf($response)->yes() ? null : new ObjectType(Exception::class);
    }
}
