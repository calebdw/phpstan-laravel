<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\FactoryHelper;
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
final class FactoryOfType implements CompoundType, LateResolvableType
{
    /** @phpstan-ignore phpstanApi.trait (LateResolvableType has no public substitute) */
    use LateResolvableTypeTrait;

    public function __construct(private Type $type, private FactoryHelper $factoryHelper)
    {
    }

    protected function getResult(): Type
    {
        return $this->factoryHelper->determineFactoryType(
            $this->type,
            static fn ($factory) => new ModelFactoryType($factory->getName(), null, $factory),
        );
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
        return 'factory-of<' . $this->type->describe($level) . '>';
    }

    /** @param callable(Type): Type $cb */
    public function traverse(callable $cb): Type
    {
        $type = $cb($this->type);

        return $this->type === $type ? $this : new self($type, $this->factoryHelper);
    }

    public function traverseSimultaneously(Type $right, callable $cb): Type
    {
        if (! $right instanceof self) {
            return $this;
        }

        $type = $cb($this->type, $right->type);

        return $this->type === $type ? $this : new self($type, $this->factoryHelper);
    }

    public function toPhpDocNode(): TypeNode
    {
        return new GenericTypeNode(new IdentifierTypeNode('factory-of'), [$this->type->toPhpDocNode()]);
    }

    public function generalize(GeneralizePrecision $precision): Type
    {
        return $this->traverse(static fn (Type $type) => $type->generalize($precision));
    }
}
