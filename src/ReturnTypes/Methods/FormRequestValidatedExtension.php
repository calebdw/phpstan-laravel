<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use CalebDW\PhpstanLaravel\Support\ValidationHelper;
use Illuminate\Foundation\Http\FormRequest;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class FormRequestValidatedExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ValidationHelper $validationHelper,
        private ColumnHelper $columnHelper,
    ) {
    }

    public function getClass(): string
    {
        return FormRequest::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'validated';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOn = $scope->getType($methodCall->var);
        $shapes   = [];

        foreach ($calledOn->getObjectClassReflections() as $class) {
            $shape = $this->validationHelper->validatedShape($class);

            if ($shape === null) {
                continue;
            }

            $shapes[] = $shape;
        }

        if ($shapes === []) {
            return null;
        }

        $shape  = TypeCombinator::union(...$shapes);
        $keyArg = $methodCall->getArg('key', 0);

        if ($keyArg === null) {
            return $shape;
        }

        // validated($key, $default) is data_get() on the validated array.
        return $this->columnHelper->dataGet(
            $shape,
            $scope->getType($keyArg->value),
            $methodCall->getArg('default', 1),
            $scope,
        );
    }
}
