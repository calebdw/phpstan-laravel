<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes;

use CalebDW\PhpstanLaravel\Methods\BuilderHelper;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function collect;
use function in_array;

#[AutowiredService]
class NewModelQueryDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private BuilderHelper $builderHelper)
    {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), [
            'newQuery',
            'newModelQuery',
            'newQueryWithoutRelationships',
            'newQueryWithoutScopes',
            'newQueryWithoutScope',
            'newQueryForRestoration',
        ], true);
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $calledOnType     = $scope->getType($methodCall->var);
        $classReflections = $calledOnType->getObjectClassReflections();

        if ($classReflections === []) {
            return null;
        }

        return collect($classReflections)
            ->filter(static fn ($r) => $r->is(Model::class))
            ->map(static fn ($r) => $r->getName())
            ->pipe(function ($models) {
                if ($models->isEmpty()) {
                    return null;
                }

                return $this->builderHelper->getBuilderTypeForModels($models->all());
            });
    }
}
