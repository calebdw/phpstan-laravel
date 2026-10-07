<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Reflection\SimpleParameterReflection;
use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use Closure;
use Illuminate\Database\Eloquent\Model;
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
use PHPStan\Type\StringType;
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
        if (! $this->namesRelations()) {
            return $this->bound;
        }

        $closure = new ObjectType(Closure::class);
        $array   = new ArrayType(new MixedType(), new MixedType());
        $value   = TypeCombinator::intersect($this->bound, $array)->getIterableValueType();

        if (! $this->relations->isConstantArray()->yes()) {
            // A key that cannot be read takes the keys beside it with it, as
            // PHPStan has already collapsed the array by the time we see it.
            // The relation is still one of the model's, which is worth more to
            // the closure than the mixed the bound would hand it.
            $members = [];

            foreach (TypeUtils::flattenTypes($value) as $member) {
                $members[] = $closure->isSuperTypeOf($member)->yes()
                    ? $this->closureFor(new StringType())
                    : $member;
            }

            return TypeCombinator::union(
                ...TypeUtils::flattenTypes(TypeCombinator::remove($this->bound, $array)),
                ...[new ArrayType(new MixedType(), TypeCombinator::union(...$members))],
            );
        }

        $arrays = [];

        foreach ($this->relations->getConstantArrays() as $constantArray) {
            $builder = ConstantArrayTypeBuilder::createEmpty();

            foreach ($constantArray->getKeyTypes() as $i => $key) {
                $keyValue = $value;

                if ($key->isString()->yes() && $closure->isSuperTypeOf($constantArray->getValueTypes()[$i])->yes()) {
                    $keyValue = $this->closureFor($key);
                }

                $builder->setOffsetValueType($key, $keyValue, $constantArray->isOptionalKey($i));
            }

            $arrays[] = $builder->getArray();
        }

        return TypeCombinator::union(...$arrays);
    }

    /**
     * Whether the model is one whose relations could be named.
     *
     * A query that never said which model it runs on has the base model here,
     * which declares none, so no key names one either. Every name resolves to
     * a bare `Relation`, which then rejects the `withTrashed()` a `BelongsTo`
     * would have taken, so the bound's mixed is the honest answer instead.
     */
    private function namesRelations(): bool
    {
        // A template stands for whatever model the caller has, which does
        // name them.
        if (TypeUtils::containsTemplateType($this->type)) {
            return true;
        }

        $names = $this->type->getObjectClassNames();

        if ($names === []) {
            return false;
        }

        foreach ($names as $name) {
            if ($name === Model::class) {
                return false;
            }
        }

        return true;
    }

    /** The callback a relation name, or any of them, hands its query to. */
    private function closureFor(Type $relationNames): ClosureType
    {
        return new ClosureType(
            [new SimpleParameterReflection('query', new RelationOfType($this->type, $relationNames, $this->builderHelper))],
            new MixedType(),
        );
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
