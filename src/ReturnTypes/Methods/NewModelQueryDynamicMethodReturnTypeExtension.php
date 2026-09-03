<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function collect;
use function in_array;

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

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $classReflections = $scope->getType($methodCall->var)->getObjectClassReflections();

        return collect($classReflections)
            ->filter(static fn ($r) => $r->is(Model::class))
            ->map(static fn ($r) => $r->getName())
            ->pipe(fn ($m) => $m->isEmpty() ? null : $this->builderHelper->getBuilderTypeForModels($m->all()));
    }
}
