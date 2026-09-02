<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use stdClass;

use function array_map;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

final class ConfigHelper
{
    public function __construct(
        private ConfigParser $configParser,
        private ContainerHelper $containerHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function determineConfigType(
        FunctionReflection|MethodReflection $reflection,
        FuncCall|MethodCall|StaticCall $call,
        Scope $scope,
    ): Type|null {
        $repository = $this->getRepository();

        if (! $repository && ! $this->configParser->hasDirectories()) {
            return null;
        }

        if ($reflection->getName() === 'all') {
            return $repository ? $this->getTypeFromValue($repository->all()) : null;
        }

        $args    = $call->getArgs();
        $key     = $args[0]->value ?? null;
        $default = $args[1]->value ?? null;

        if (! $key) {
            return null;
        }

        $keyType = $scope->getType($key);

        if ($keyType->isArray()->yes()) {
            // helper function called with array to set values
            if ($reflection->getName() === 'config') {
                return null;
            }

            $constantArrays = $keyType->getConstantArrays();

            if (count($constantArrays) !== count($keyType->getArrays())) {
                return null;
            }

            return TypeCombinator::union(...array_map(
                function ($constantArray) use ($repository, $scope): Type {
                    $array = $this->getArrayFromConstantArrayType($constantArray);

                    if (! $array) {
                        return new MixedType();
                    }

                    $builder = ConstantArrayTypeBuilder::createEmpty();

                    if (count($array) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
                        $builder->degradeToGeneralArray(true);
                    }

                    foreach ($array as $index => $value) {
                        $key     = is_int($index) ? $value : $index;
                        $default = is_int($index) ? null : $value;

                        if (! is_string($key)) {
                            return new MixedType();
                        }

                        $builder->setOffsetValueType(
                            new ConstantStringType($key),
                            $this->resolveKey($key, $repository, $scope)
                                ?? $this->getTypeFromValue($default),
                        );
                    }

                    return $builder->getArray();
                },
                $constantArrays,
            ));
        }

        if (! $keyType->isString()->yes()) {
            return null;
        }

        $keys = $this->typeHelper->constantStrings($keyType);

        if ($keys === []) {
            return null;
        }

        $defaultType = $default ? $scope->getType($default) : new NullType();

        // default might be a closure
        if (count($defaultType->getConstantScalarValues()) !== 1) {
            return null;
        }

        $configType = TypeCombinator::union(...array_map(
            fn (string $key): Type => $this->resolveKey($key, $repository, $scope)
                ?? new MixedType(),
            $keys,
        ));

        if ($reflection->getName() === 'collection') {
            return $configType->isArray()->yes()
                ? new GenericObjectType(Collection::class, [
                    $configType->getIterableKeyType(),
                    $configType->getIterableValueType(),
                ])
                : null;
        }

        if ($reflection->getName() === 'array') {
            return $configType;
        }

        return TypeCombinator::union($configType, $defaultType);
    }

    /**
     * Resolves the type of a single config key, for callers that have a
     * key in hand rather than a call to take it from.
     */
    public function getKeyType(string $key, Scope $scope): Type|null
    {
        return $this->resolveKey($key, $this->getRepository(), $scope);
    }

    /**
     * Resolves the type of the given key from the booted container,
     * falling back to statically parsing the configured directories
     * for keys the container does not know about.
     */
    private function resolveKey(string $key, Repository|null $repository, Scope $scope): Type|null
    {
        if ($repository) {
            $default = new stdClass();
            $value   = $repository->get($key, $default);

            if ($value !== $default) {
                return $this->getTypeFromValue($value);
            }
        }

        return $this->configParser->getType($key, $scope);
    }

    private function getRepository(): Repository|null
    {
        $repository = $this->containerHelper->resolve('config');

        return $repository instanceof Repository ? $repository : null;
    }

    private function getTypeFromValue(mixed $value, bool $constant = false): Type
    {
        // Not using `$scope->getTypeFromValue()` as we don't
        // want array values to be constant types given
        // that the value can change for different envs.
        return match (true) {
            is_int($value) => $constant ? new ConstantIntegerType($value) : new IntegerType(),
            is_float($value) => $constant ? new ConstantFloatType($value) : new FloatType(),
            is_bool($value) => $constant ? new ConstantBooleanType($value) : new BooleanType(),
            is_string($value) => $constant ? new ConstantStringType($value) : new StringType(),
            is_array($value) => (function () use ($value) {
                $arrayBuilder = ConstantArrayTypeBuilder::createEmpty();

                if (count($value) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
                    $arrayBuilder->degradeToGeneralArray(true);
                }

                foreach ($value as $k => $v) {
                    $arrayBuilder->setOffsetValueType(
                        $this->getTypeFromValue($k, constant: true),
                        $this->getTypeFromValue($v),
                    );
                }

                return $arrayBuilder->getArray();
            })(),
            is_object($value) => new ObjectType($value::class),
            default => new MixedType(),
        };
    }

    /** @return array<int|string, mixed>|null */
    private function getArrayFromConstantArrayType(ConstantArrayType $type): array|null
    {
        $keys   = $type->getKeyTypes();
        $values = $type->getValueTypes();

        $array = [];

        foreach ($keys as $index => $key) {
            $valueType = $values[$index];

            $arrays  = $valueType->getConstantArrays();
            $scalars = $valueType->getConstantScalarValues();

            if (count($arrays)) {
                $value = $this->getArrayFromConstantArrayType($arrays[0]);
            } elseif (count($scalars)) {
                $value = $scalars[0];
            } else {
                return null;
            }

            $array[$key->getValue()] = $value;
        }

        return $array;
    }
}
