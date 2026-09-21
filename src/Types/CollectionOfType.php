<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\CompoundType;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\IntegerType;
use PHPStan\Type\LateResolvableType;
use PHPStan\Type\NeverType;
use PHPStan\Type\StringType;
use PHPStan\Type\Traits\LateResolvableTypeTrait;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;

use function array_merge;

/** @phpstan-ignore phpstanApi.interface (LateResolvableType has no public substitute) */
final class CollectionOfType implements CompoundType, LateResolvableType
{
    /** @phpstan-ignore phpstanApi.trait (LateResolvableType has no public substitute) */
    use LateResolvableTypeTrait;

    public function __construct(
        private Type $type,
        private CollectionHelper $collectionHelper,
        private Type|null $keyType = null,
    ) {
    }

    protected function getResult(): Type
    {
        return $this->collectionHelper->determineCollectionTypeFromModels(
            $this->type,
            $this->keyType ?? new BenevolentUnionType([new IntegerType(), new StringType()]),
        ) ?? new NeverType();
    }

    public function isResolvable(): bool
    {
        return ! TypeUtils::containsTemplateType($this->type)
            && ($this->keyType === null || ! TypeUtils::containsTemplateType($this->keyType));
    }

    /** @inheritDoc */
    public function getReferencedClasses(): array
    {
        return array_merge($this->type->getReferencedClasses(), $this->keyType?->getReferencedClasses() ?? []);
    }

    /** @inheritDoc */
    public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance): array
    {
        return array_merge(
            $this->type->getReferencedTemplateTypes($positionVariance),
            $this->keyType?->getReferencedTemplateTypes($positionVariance) ?? [],
        );
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self
            && $this->type->equals($type->type)
            && ($this->keyType === null
                ? $type->keyType === null
                : $type->keyType !== null && $this->keyType->equals($type->keyType));
    }

    public function describe(VerbosityLevel $level): string
    {
        return 'collection-of<' . ($this->keyType === null ? '' : $this->keyType->describe($level) . ', ')
            . $this->type->describe($level) . '>';
    }

    /** @param callable(Type): Type $cb */
    public function traverse(callable $cb): Type
    {
        $type    = $cb($this->type);
        $keyType = $this->keyType === null ? null : $cb($this->keyType);

        if ($this->type === $type && $this->keyType === $keyType) {
            return $this;
        }

        return new self($type, $this->collectionHelper, $keyType);
    }

    public function traverseSimultaneously(Type $right, callable $cb): Type
    {
        if (! $right instanceof self || ($this->keyType === null) !== ($right->keyType === null)) {
            return $this;
        }

        $type    = $cb($this->type, $right->type);
        $keyType = $this->keyType !== null && $right->keyType !== null
            ? $cb($this->keyType, $right->keyType)
            : null;

        if ($this->type === $type && $this->keyType === $keyType) {
            return $this;
        }

        return new self($type, $this->collectionHelper, $keyType);
    }

    public function toPhpDocNode(): TypeNode
    {
        $types = $this->keyType === null
            ? [$this->type->toPhpDocNode()]
            : [$this->keyType->toPhpDocNode(), $this->type->toPhpDocNode()];

        return new GenericTypeNode(new IdentifierTypeNode('collection-of'), $types);
    }

    public function generalize(GeneralizePrecision $precision): Type
    {
        return $this->traverse(static fn (Type $type) => $type->generalize($precision));
    }
}
