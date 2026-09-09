<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\CompoundType;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\LateResolvableType;
use PHPStan\Type\Traits\LateResolvableTypeTrait;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;

use function array_merge;

/** @phpstan-ignore phpstanApi.interface (LateResolvableType has no public substitute) */
final class BuilderOfType implements CompoundType, LateResolvableType
{
    /** @phpstan-ignore phpstanApi.trait (LateResolvableType has no public substitute) */
    use LateResolvableTypeTrait;

    public function __construct(
        private Type $type,
        private BuilderHelper $builderHelper,
        private Type|null $relationNames = null,
    ) {
    }

    protected function getResult(): Type
    {
        return $this->builderHelper->determineBuilderType($this->type, $this->relationNames);
    }

    public function isResolvable(): bool
    {
        return ! TypeUtils::containsTemplateType($this->type)
            && ($this->relationNames === null || ! TypeUtils::containsTemplateType($this->relationNames));
    }

    /** @inheritDoc */
    public function getReferencedClasses(): array
    {
        return array_merge($this->type->getReferencedClasses(), $this->relationNames?->getReferencedClasses() ?? []);
    }

    /** @inheritDoc */
    public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
    {
        return array_merge(
            $this->type->getReferencedTemplateTypes($positionVariance),
            $this->relationNames?->getReferencedTemplateTypes($positionVariance) ?? [],
        );
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self
            && $this->type->equals($type->type)
            && ($this->relationNames === null
                ? $type->relationNames === null
                : $type->relationNames !== null && $this->relationNames->equals($type->relationNames));
    }

    public function describe(VerbosityLevel $level): string
    {
        return 'builder-of<' . $this->type->describe($level)
            . ($this->relationNames === null ? '' : ', ' . $this->relationNames->describe($level)) . '>';
    }

    /** @param callable(Type): Type $cb */
    public function traverse(callable $cb): Type
    {
        $type          = $cb($this->type);
        $relationNames = $this->relationNames === null ? null : $cb($this->relationNames);

        if ($this->type === $type && $this->relationNames === $relationNames) {
            return $this;
        }

        return new self($type, $this->builderHelper, $relationNames);
    }

    public function traverseSimultaneously(Type $right, callable $cb): Type
    {
        if (! $right instanceof self || ($this->relationNames === null) !== ($right->relationNames === null)) {
            return $this;
        }

        $type          = $cb($this->type, $right->type);
        $relationNames = $this->relationNames !== null && $right->relationNames !== null
            ? $cb($this->relationNames, $right->relationNames)
            : null;

        if ($this->type === $type && $this->relationNames === $relationNames) {
            return $this;
        }

        return new self($type, $this->builderHelper, $relationNames);
    }

    public function toPhpDocNode(): TypeNode
    {
        $types = [$this->type->toPhpDocNode()];

        if ($this->relationNames !== null) {
            $types[] = $this->relationNames->toPhpDocNode();
        }

        return new GenericTypeNode(new IdentifierTypeNode('builder-of'), $types);
    }

    public function generalize(GeneralizePrecision $precision): Type
    {
        return $this->traverse(static fn (Type $type) => $type->generalize($precision));
    }
}
