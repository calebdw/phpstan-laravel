<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Types\BuilderOfType;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;

use function in_array;

final class ModelDynamicStaticMethodReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(
        private BuilderHelper $builderHelper,
        private CollectionHelper $collectionHelper,
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getClass(): string
    {
        return Model::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        $name = $methodReflection->getName();

        if ($name === '__construct') {
            return false;
        }

        // Another extension handles this case
        if (Str::startsWith($name, 'find')) {
            return false;
        }

        if (in_array($name, ['get', 'hydrate', 'fromQuery'], true)) {
            return true;
        }

        return $this->reflectionProvider->getClass(Model::class)->hasNativeMethod($name);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type|null
    {
        $method = $methodReflection->getDeclaringClass()
            ->getMethod($methodReflection->getName(), $scope);

        $returnType = ParametersAcceptorSelector::selectFromArgs($scope, $methodCall->getArgs(), $method->getVariants())->getReturnType();

        if ($returnType instanceof NeverType) {
            return null;
        }

        $modelType = $this->calledOnType($methodCall, $scope);

        if ((new ObjectType(EloquentBuilder::class))->isSuperTypeOf($returnType)->yes()) {
            if (! (new ObjectType(Model::class))->isSuperTypeOf($modelType)->yes()) {
                return null;
            }

            return new BuilderOfType(
                $modelType instanceof ThisType ? $modelType->getStaticObjectType() : $modelType,
                $this->builderHelper,
            );
        }

        if (in_array(Collection::class, $returnType->getReferencedClasses(), true)) {
            $collection = $this->collectionHelper->determineCollectionTypeFromModels($modelType);

            if ($collection !== null) {
                return $collection;
            }
        }

        // Nothing to contribute: PHPStan resolves `static` and `$this` against the
        // called-on type itself, which a return type read off the declaring class
        // would throw away.
        return null;
    }

    /**
     * `parent::` forwards late static binding, but resolving the name gives the
     * parent class, which would hand back a builder of the wrong model.
     */
    private function calledOnType(StaticCall $methodCall, Scope $scope): Type
    {
        if (! $methodCall->class instanceof Name) {
            return $scope->getType($methodCall->class)->getObjectTypeOrClassStringObjectType();
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection !== null && $methodCall->class->toLowerString() === 'parent') {
            return new StaticType($classReflection);
        }

        return $scope->resolveTypeByName($methodCall->class);
    }
}
