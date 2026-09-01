<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Methods\MacroMethodsClassReflectionExtension;
use CalebDW\PhpstanLaravel\Reflection\DynamicWhereParameterReflection;
use CalebDW\PhpstanLaravel\Reflection\EloquentBuilderMethodReflection;
use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VerbosityLevel;

use function array_flip;
use function array_key_exists;
use function array_shift;
use function collect;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function preg_split;
use function strtolower;
use function substr;
use function ucfirst;

use const PREG_SPLIT_DELIM_CAPTURE;

final class BuilderHelper
{
    public const array MODEL_RETRIEVAL_METHODS = ['first', 'find', 'findMany', 'findOrFail', 'firstOrFail', 'sole'];

    public const array MODEL_CREATION_METHODS = ['make', 'create', 'forceCreate', 'findOrNew', 'firstOrNew', 'updateOrCreate', 'firstOrCreate', 'createOrFirst'];

    /** @var array<lowercase-string, int> */
    private array $passthru;

    /** @var array<string, string> */
    private array $builderNames = [];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private bool $checkProperties,
        private MacroMethodsClassReflectionExtension $macroMethodsClassReflectionExtension,
    ) {
    }

    public function dynamicWhere(string $methodName, Type $returnObject, Type|null $modelType = null): EloquentBuilderMethodReflection|null
    {
        if (! Str::startsWith($methodName, 'where')) {
            return null;
        }

        if ($this->checkProperties) {
            $modelType ??= $this->getModelType($returnObject);

            if ($modelType !== null) {
                $finder = substr($methodName, 5);

                $segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

                if ($segments !== false) {
                    $trinaryLogic = TrinaryLogic::createYes();

                    foreach ($segments as $segment) {
                        if ($segment === 'And' || $segment === 'Or') {
                            continue;
                        }

                        $trinaryLogic = $trinaryLogic->and($modelType->hasInstanceProperty(Str::snake($segment)));
                    }

                    if (! $trinaryLogic->yes()) {
                        return null;
                    }
                }
            }
        }

        $classReflection = $this->reflectionProvider->getClass(QueryBuilder::class);

        if (! $classReflection->hasNativeMethod('dynamicWhere')) {
            throw new ShouldNotHappenException(<<<'TXT'
                Method 'dynamicWhere' not found in QueryBuilder reflection.
                This is known to happen when this extension scans the stubs from
                the IDE-Helper package.
                TXT);
        }

        return new EloquentBuilderMethodReflection(
            $methodName,
            $classReflection,
            [new DynamicWhereParameterReflection()],
            $returnObject,
            true,
        );
    }

    /**
     * This method mimics the `EloquentBuilder::__call` method.
     * Does not handle the case where $methodName exists in `EloquentBuilder`,
     * that should be checked by caller before calling this method.
     *
     * @param  ClassReflection $eloquentBuilder Can be `EloquentBuilder` or a custom builder extending it.
     *
     * @throws MissingMethodFromReflectionException
     * @throws ShouldNotHappenException
     */
    public function searchOnEloquentBuilder(ClassReflection $eloquentBuilder, string $methodName, Type $modelType): MethodReflection|null
    {
        // Check for macros first
        if ($this->macroMethodsClassReflectionExtension->hasMethod($eloquentBuilder, $methodName)) {
            return $this->macroMethodsClassReflectionExtension->getMethod($eloquentBuilder, $methodName);
        }

        $scopeName = 'scope' . ucfirst($methodName);

        foreach ($modelType->getObjectClassReflections() as $reflection) {
            // Check for Scope attribute
            if ($reflection->hasNativeMethod($methodName)) {
                $methodReflection  = $reflection->getNativeMethod($methodName);
                $hasScopeAttribute = false;
                foreach ($methodReflection->getAttributes() as $attribute) {
                    if ($attribute->getName() === Scope::class) {
                        $hasScopeAttribute = true;
                        break;
                    }
                }

                if (! $methodReflection->isPublic() && $hasScopeAttribute) {
                    $parametersAcceptor = $methodReflection->getVariants()[0];

                    $parameters = $parametersAcceptor->getParameters();
                    // We shift the parameters,
                    // because first parameter is the Builder
                    array_shift($parameters);

                    $returnType = $parametersAcceptor->getReturnType();

                    return new EloquentBuilderMethodReflection(
                        $methodName,
                        $methodReflection->getDeclaringClass(),
                        $parameters,
                        $returnType,
                        $parametersAcceptor->isVariadic(),
                    );
                }
            }

            // Check for @method phpdoc tags
            if (array_key_exists($scopeName, $reflection->getMethodTags())) {
                $methodTag = $reflection->getMethodTags()[$scopeName];

                $parameters = [];
                foreach ($methodTag->getParameters() as $parameterName => $parameterTag) {
                    $parameters[] = new SimpleParameterReflection(
                        $parameterName,
                        $parameterTag->getType(),
                        $parameterTag->isOptional(),
                        $parameterTag->passedByReference(),
                        $parameterTag->isVariadic(),
                        $parameterTag->getDefaultValue(),
                    );
                }

                // We shift the parameters,
                // because first parameter is the Builder
                array_shift($parameters);

                return new EloquentBuilderMethodReflection(
                    $scopeName,
                    $reflection,
                    $parameters,
                    $methodTag->getReturnType(),
                );
            }

            if ($reflection->hasNativeMethod($scopeName)) {
                $methodReflection   = $reflection->getNativeMethod($scopeName);
                $parametersAcceptor = $methodReflection->getVariants()[0];

                $parameters = $parametersAcceptor->getParameters();
                // We shift the parameters,
                // because first parameter is the Builder
                array_shift($parameters);

                $returnType = $parametersAcceptor->getReturnType();

                return new EloquentBuilderMethodReflection(
                    $scopeName,
                    $methodReflection->getDeclaringClass(),
                    $parameters,
                    $returnType,
                    $parametersAcceptor->isVariadic(),
                );
            }
        }

        $queryBuilderReflection = $this->reflectionProvider->getClass(QueryBuilder::class);

        if ($this->methodIsBuilderPassthru($methodName) || $queryBuilderReflection->hasNativeMethod($methodName)) {
            return $queryBuilderReflection->getNativeMethod($methodName);
        }

        // Check for query builder macros
        if ($this->macroMethodsClassReflectionExtension->hasMethod($queryBuilderReflection, $methodName)) {
            return $this->macroMethodsClassReflectionExtension->getMethod($queryBuilderReflection, $methodName);
        }

        return $this->dynamicWhere($methodName, $this->getBuilderType($eloquentBuilder->getName(), $modelType), $modelType);
    }

    public function getModelType(ClassReflection|Type $type): Type|null
    {
        if ($type instanceof ClassReflection) {
            $type = $type->getObjectType();
        }

        $modelType = $type->getTemplateType(EloquentBuilder::class, 'TModel');

        if ($modelType instanceof ErrorType) {
            $modelType = $type->getTemplateType(Relation::class, 'TRelatedModel');
        }

        return $modelType instanceof ErrorType ? null : $modelType;
    }

    public function getBuilderType(string $builderClassName, Type $modelType): ObjectType
    {
        if (! $this->reflectionProvider->getClass($builderClassName)->isGeneric()) {
            return new ObjectType($builderClassName);
        }

        return new GenericObjectType($builderClassName, [$modelType]);
    }

    public function determineBuilderName(string $modelClassName): string
    {
        return $this->builderNames[$modelClassName] ??= $this->resolveBuilderName($modelClassName);
    }

    private function resolveBuilderName(string $modelClassName): string
    {
        $modelReflection = $this->reflectionProvider->getClass($modelClassName);
        $method          = $modelReflection->getNativeMethod('newEloquentBuilder');

        if ($method->getDeclaringClass()->getName() === Model::class) {
            $attrs = $modelReflection->getNativeReflection()->getAttributes(UseEloquentBuilder::class);

            if ($attrs !== []) {
                $expr =  $attrs[0]->getArgumentsExpressions()[0];

                if ($expr instanceof ClassConstFetch && $expr->class instanceof Name) {
                    return $expr->class->toString();
                }
            }
        }

        $returnType = $method->getVariants()[0]->getReturnType();

        if (in_array(EloquentBuilder::class, $returnType->getReferencedClasses(), true)) {
            return EloquentBuilder::class;
        }

        $classNames = $returnType->getObjectClassNames();

        if (count($classNames) === 1) {
            return $classNames[0];
        }

        return $returnType->describe(VerbosityLevel::value());
    }

    /**
     * @param  array<int, string|TypeWithClassName>|string|TypeWithClassName $models
     *
     * @return ($models is array<int, string|TypeWithClassName> ? Type : ObjectType)
     */
    public function getBuilderTypeForModels(array|string|TypeWithClassName $models): Type
    {
        // A single model is by far the common case, and does not need grouping.
        if (! is_array($models)) {
            return is_string($models)
                ? $this->getBuilderType($this->determineBuilderName($models), new ObjectType($models))
                : $this->getBuilderType($this->determineBuilderName($models->getClassName()), $models);
        }

        return collect()
            ->wrap($models)
            ->unique()
            ->mapWithKeys(static function ($model) {
                if (is_string($model)) {
                    return [$model => new ObjectType($model)];
                }

                return [$model->getClassName() => $model];
            })
            ->mapToGroups(fn ($type, $class) => [$this->determineBuilderName($class) => $type])
            ->map(fn ($models, $builder) => $this->getBuilderType($builder, TypeCombinator::union(...$models)))
            ->values()
            ->pipe(static fn ($types) => TypeCombinator::union(...$types));
    }

    public function methodIsBuilderPassthru(string $methodName): bool
    {
        return isset($this->getEloquentBuilderPassthru()[strtolower($methodName)]);
    }

    /** @return array<lowercase-string, int> */
    private function getEloquentBuilderPassthru(): array
    {
        if (isset($this->passthru)) {
            return $this->passthru;
        }

        if (! $this->reflectionProvider->hasClass(EloquentBuilder::class)) {
            return $this->passthru = [];
        }

        /** @var list<lowercase-string> $passthru */
        $passthru = $this->reflectionProvider
            ->getClass(EloquentBuilder::class)
            ->getNativeReflection()
            ->getDefaultProperties()['passthru'] ?? [];

        // Flipped so lookups are a hash hit rather than a linear scan.
        return $this->passthru = array_flip($passthru);
    }
}
