<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Closure;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;

use function collect;

final class TypeHelper
{
    /**
     * @param callable(ClassReflection): bool $filter
     *
     * @return list<string>
     */
    public function classNames(Type $type, callable $filter): array
    {
        return collect($type->getObjectClassReflections())
            ->filter($filter)
            ->map(static fn ($c) => $c->getDisplayName())
            ->values()
            ->all();
    }

    /** @param class-string|array<class-string> $classes */
    public function isCalledOn(Type $type, array|string $classes): bool
    {
        $classes = (array) $classes;

        return collect($type->getObjectClassReflections())
            ->contains(static fn ($r) => collect($classes)->contains(static fn ($c) => $r->is($c)));
    }

    /** @param class-string $trait */
    public function usesTrait(Type $type, string $trait): bool
    {
        return collect($type->getObjectClassReflections())
            ->contains(static fn ($c) => $c->hasTraitUse($trait));
    }

    public function hasMethod(Type $type, string $name, bool $native = false): bool
    {
        if (! $native) {
            return $type->hasMethod($name)->yes();
        }

        return collect($type->getObjectClassReflections())->every(static fn ($c) => $c->hasNativeMethod($name));
    }

    public function hasProperty(Type $type, string $name, bool $native = false): bool
    {
        if (! $native) {
            return $type->hasInstanceProperty($name)->yes();
        }

        return collect($type->getObjectClassReflections())->every(static fn ($c) => $c->hasNativeProperty($name));
    }

    /** @return list<Type> */
    public function constantValues(Type $type): array
    {
        return collect($type->getConstantScalarTypes())
            ->concat(
                collect($type->getConstantArrays())
                    ->flatMap(static fn ($a) => $a->getValueTypes())
                    ->flatMap($this->constantValues(...)),
            )
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function constantStrings(Type $type): array
    {
        return collect($this->constantValues($type))
            ->flatMap(static fn ($t) => $t->getConstantStrings())
            ->map(static fn ($s) => $s->getValue())
            ->values()
            ->all();
    }

    /**
     * What `value()` hands back, and with it every default that goes through
     * it. Only a Closure is called: a callable string such as `'time'` is a
     * value like any other, and comes back as it was given.
     */
    public function valueOf(Type $type, Scope $scope): Type
    {
        $closure = new ObjectType(Closure::class);

        return TypeTraverser::map($type, static function (Type $type, callable $traverse) use ($closure, $scope): Type {
            if (! $type->isCallable()->yes()) {
                return $traverse($type);
            }

            $isClosure = $closure->isSuperTypeOf($type);

            if ($isClosure->no()) {
                return $type;
            }

            $returnType = $type->getCallableParametersAcceptors($scope)[0]->getReturnType();

            return $isClosure->yes() ? $returnType : TypeCombinator::union($type, $returnType);
        });
    }
}
