<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function collect;

final class TypeHelper
{
    /** @param class-string|list<class-string> $classes */
    public function isCalledOn(Type $type, array|string $classes): bool
    {
        return collect((array) $classes)
            ->contains(static fn ($c) => (new ObjectType($c))->isSuperTypeOf($type)->yes());
    }

    /** @param class-string $trait */
    public function usesTrait(Type $type, string $trait): bool
    {
        return collect($type->getObjectClassReflections())
            ->contains(static fn ($c) => $c->hasTraitUse($trait));
    }

    /** @return list<string> */
    public function constantStrings(Type $type): array
    {
        return collect($type->getConstantStrings())
            ->map(static fn ($s) => $s->getValue())
            ->concat(
                collect($type->getConstantArrays())
                    ->flatMap(static fn ($a) => $a->getValueTypes())
                    ->flatMap($this->constantStrings(...)),
            )
            ->values()
            ->all();
    }
}
