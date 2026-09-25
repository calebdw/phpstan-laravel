<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\CompoundType;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\LateResolvableType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Traits\LateResolvableTypeTrait;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;

use function array_merge;

/** @phpstan-ignore phpstanApi.interface (LateResolvableType has no public substitute) */
final class RelationOfType implements CompoundType, LateResolvableType
{
    /** @phpstan-ignore phpstanApi.trait (LateResolvableType has no public substitute) */
    use LateResolvableTypeTrait;

    public function __construct(
        private Type $type,
        private Type $relationNames,
        private BuilderHelper $builderHelper,
    ) {
    }

    protected function getResult(): Type
    {
        return $this->builderHelper->relationType($this->type, $this->relationNames)
            ?? new GenericObjectType(Relation::class, [new ObjectType(Model::class), $this->type]);
    }

    public function isResolvable(): bool
    {
        return ! TypeUtils::containsTemplateType($this->type)
            && ! TypeUtils::containsTemplateType($this->relationNames);
    }

    /** @inheritDoc */
    public function getReferencedClasses(): array
    {
        return array_merge($this->type->getReferencedClasses(), $this->relationNames->getReferencedClasses());
    }

    /** @inheritDoc */
    public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
    {
        return array_merge(
            $this->type->getReferencedTemplateTypes($positionVariance),
            $this->relationNames->getReferencedTemplateTypes($positionVariance),
        );
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self
            && $this->type->equals($type->type)
            && $this->relationNames->equals($type->relationNames);
    }

    public function describe(VerbosityLevel $level): string
    {
        return 'relation-of<' . $this->type->describe($level) . ', ' . $this->relationNames->describe($level) . '>';
    }

    /** @param callable(Type): Type $cb */
    public function traverse(callable $cb): Type
    {
        $type          = $cb($this->type);
        $relationNames = $cb($this->relationNames);

        if ($this->type === $type && $this->relationNames === $relationNames) {
            return $this;
        }

        return new self($type, $relationNames, $this->builderHelper);
    }

    public function traverseSimultaneously(Type $right, callable $cb): Type
    {
        if (! $right instanceof self) {
            return $this;
        }

        $type          = $cb($this->type, $right->type);
        $relationNames = $cb($this->relationNames, $right->relationNames);

        if ($this->type === $type && $this->relationNames === $relationNames) {
            return $this;
        }

        return new self($type, $relationNames, $this->builderHelper);
    }

    public function toPhpDocNode(): TypeNode
    {
        return new GenericTypeNode(new IdentifierTypeNode('relation-of'), [
            $this->type->toPhpDocNode(),
            $this->relationNames->toPhpDocNode(),
        ]);
    }

    public function generalize(GeneralizePrecision $precision): Type
    {
        return $this->traverse(static fn (Type $type) => $type->generalize($precision));
    }
}
