<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use CalebDW\PhpstanLaravel\Types\BuilderOfType;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;

final class NewModelQueryDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private const array METHODS = [
        'newQuery'                       => 1,
        'newModelQuery'                  => 1,
        'newQueryWithoutRelationships'   => 1,
        'newQueryWithoutScopes'          => 1,
        'newQueryWithoutScope'           => 1,
        'newQueryForRestoration'         => 1,
    ];

    public function __construct(private BuilderHelper $builderHelper)
    {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return isset(self::METHODS[$methodReflection->getName()]);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $calledOnType = $scope->getType($methodCall->var);

        if ($calledOnType instanceof ThisType) {
            $calledOnType = new StaticType($calledOnType->getClassReflection());
        }

        if (! (new ObjectType(Model::class))->isSuperTypeOf($calledOnType)->yes()) {
            return null;
        }

        return new BuilderOfType($calledOnType, $this->builderHelper);
    }
}
