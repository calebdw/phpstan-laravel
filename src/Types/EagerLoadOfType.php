<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use Closure;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\ArrayType;
use PHPStan\Type\ClosureType;
use PHPStan\Type\CompoundType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\LateResolvableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Traits\LateResolvableTypeTrait;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;

use function array_merge;

/** @phpstan-ignore phpstanApi.interface (LateResolvableType has no public substitute) */
final class EagerLoadOfType implements CompoundType, LateResolvableType
{
    /** @phpstan-ignore phpstanApi.trait (LateResolvableType has no public substitute) */
    use LateResolvableTypeTrait;

    /**
     * @param Type $bound What `$relations` accepts. The result never reuses the argument's own types:
     *                    they can carry errors from elsewhere, which PHPStan reports as an unresolvable parameter.
     */
    public function __construct(
        private Type $type,
        private Type $relations,
        private Type $bound,
        private BuilderHelper $builderHelper,
    ) {
    }

    protected function getResult(): Type
    {
        if (! $this->relations->isConstantArray()->yes()) {
            return $this->bound;
        }

        $closure = new ObjectType(Closure::class);
        $value   = TypeCombinator::intersect($this->bound, new ArrayType(new MixedType(), new MixedType()))->getIterableValueType();
        $arrays  = [];

        foreach ($this->relations->getConstantArrays() as $array) {
            $builder = ConstantArrayTypeBuilder::createEmpty();

            foreach ($array->getKeyTypes() as $i => $key) {
                $keyValue = $value;

                if ($key->isString()->yes() && $closure->isSuperTypeOf($array->getValueTypes()[$i])->yes()) {
                    $relation = new RelationOfType($this->type, $key, $this->builderHelper);
                    $keyValue = new ClosureType([new SimpleParameterReflection('query', $relation)], new MixedType());
                }

                $builder->setOffsetValueType($key, $keyValue, $array->isOptionalKey($i));
            }

            $arrays[] = $builder->getArray();
        }

        return TypeCombinator::union(...$arrays);
    }

    public function isResolvable(): bool
    {
        return ! TypeUtils::containsTemplateType($this->type)
            && ! TypeUtils::containsTemplateType($this->relations);
    }

    public function inferTemplateTypes(Type $receivedType): TemplateTypeMap
    {
        return $this->relations->inferTemplateTypes($receivedType);
    }

    /** @inheritDoc */
    public function getReferencedClasses(): array
    {
        return array_merge($this->type->getReferencedClasses(), $this->relations->getReferencedClasses());
    }

    /** @inheritDoc */
    public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
    {
        return array_merge(
            $this->type->getReferencedTemplateTypes($positionVariance),
            $this->relations->getReferencedTemplateTypes($positionVariance),
        );
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self
            && $this->type->equals($type->type)
            && $this->relations->equals($type->relations);
    }

    public function describe(VerbosityLevel $level): string
    {
        return 'eager-load-of<' . $this->type->describe($level) . ', ' . $this->relations->describe($level) . '>';
    }

    /** @param callable(Type): Type $cb */
    public function traverse(callable $cb): Type
    {
        $type      = $cb($this->type);
        $relations = $cb($this->relations);
        $bound     = $cb($this->bound);

        if ($this->type === $type && $this->relations === $relations && $this->bound === $bound) {
            return $this;
        }

        return new self($type, $relations, $bound, $this->builderHelper);
    }

    public function traverseSimultaneously(Type $right, callable $cb): Type
    {
        if (! $right instanceof self) {
            return $this;
        }

        $type      = $cb($this->type, $right->type);
        $relations = $cb($this->relations, $right->relations);
        $bound     = $cb($this->bound, $right->bound);

        if ($this->type === $type && $this->relations === $relations && $this->bound === $bound) {
            return $this;
        }

        return new self($type, $relations, $bound, $this->builderHelper);
    }

    public function toPhpDocNode(): TypeNode
    {
        return new GenericTypeNode(new IdentifierTypeNode('eager-load-of'), [
            $this->type->toPhpDocNode(),
            $this->relations->toPhpDocNode(),
        ]);
    }

    public function generalize(GeneralizePrecision $precision): Type
    {
        return $this->traverse(static fn (Type $type) => $type->generalize($precision));
    }
}
