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

/** @phpstan-ignore phpstanApi.interface (LateResolvableType has no public substitute) */
final class BuilderOfType implements CompoundType, LateResolvableType
{
    /** @phpstan-ignore phpstanApi.trait (LateResolvableType has no public substitute) */
    use LateResolvableTypeTrait;

    public function __construct(private Type $type, private BuilderHelper $builderHelper)
    {
    }

    protected function getResult(): Type
    {
        return $this->builderHelper->determineBuilderType($this->type);
    }

    public function isResolvable(): bool
    {
        return ! TypeUtils::containsTemplateType($this->type);
    }

    /** @inheritDoc */
    public function getReferencedClasses(): array
    {
        return $this->type->getReferencedClasses();
    }

    /** @inheritDoc */
    public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
    {
        return $this->type->getReferencedTemplateTypes($positionVariance);
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $this->type->equals($type->type);
    }

    public function describe(VerbosityLevel $level): string
    {
        return 'builder-of<' . $this->type->describe($level) . '>';
    }

    /** @param callable(Type): Type $cb */
    public function traverse(callable $cb): Type
    {
        $type = $cb($this->type);

        if ($this->type === $type) {
            return $this;
        }

        return new self($type, $this->builderHelper);
    }

    public function traverseSimultaneously(Type $right, callable $cb): Type
    {
        if (! $right instanceof self) {
            return $this;
        }

        $type = $cb($this->type, $right->type);

        if ($this->type === $type) {
            return $this;
        }

        return new self($type, $this->builderHelper);
    }

    public function toPhpDocNode(): TypeNode
    {
        return new GenericTypeNode(new IdentifierTypeNode('builder-of'), [$this->type->toPhpDocNode()]);
    }

    public function generalize(GeneralizePrecision $precision): Type
    {
        return $this->traverse(static fn (Type $type) => $type->generalize($precision));
    }
}
