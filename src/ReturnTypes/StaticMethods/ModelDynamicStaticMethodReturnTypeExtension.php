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

            return new BuilderOfType($modelType, $this->builderHelper);
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
     * `all()` and `query()` are inherited and resolve the model with `static`,
     * and `self::`, `$this::` and `parent::` are forwarding calls that hand it
     * straight through. None of them names the class the call is written in, so
     * how the call is spelled decides nothing - only whether a subclass can
     * exist at all does.
     *
     * A final class is where that stops being hypothetical: nothing extends it,
     * so `static` is the class, which is what PHPStan already says for a plain
     * `@return static` and only misses inside a generic argument.
     *
     * A written-out class name is not a forwarding call and keeps naming that
     * class, subclasses or not.
     */
    private function calledOnType(StaticCall $methodCall, Scope $scope): Type
    {
        $type = $this->writtenType($methodCall, $scope);

        if (! $type instanceof StaticType) {
            return $type;
        }

        return $type->getClassReflection()->isFinal()
            ? $type->getStaticObjectType()
            : new StaticType($type->getClassReflection());
    }

    private function writtenType(StaticCall $methodCall, Scope $scope): Type
    {
        if (! $methodCall->class instanceof Name) {
            return $scope->getType($methodCall->class)->getObjectTypeOrClassStringObjectType();
        }

        $classReflection = $scope->getClassReflection();

        // Resolving `parent` by name would give the parent class, and a builder
        // of the wrong model with it.
        if ($classReflection !== null && in_array($methodCall->class->toLowerString(), ['self', 'static', 'parent'], true)) {
            return new StaticType($classReflection);
        }

        // Resolving a written-out name inside its own class gives `static`,
        // which this is not: naming the class is what stops the call forwarding.
        return new ObjectType($scope->resolveName($methodCall->class));
    }
}
