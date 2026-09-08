<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PhpParser\Node\Expr\Array_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Type;

final class QueuedJobHelper
{
    public function isDispatchableClass(ClassReflection $class): bool
    {
        return ! $class->isInterface() && ! $class->isTrait() && ! $class->isAbstract();
    }

    /** @param class-string $interface */
    public function isConcrete(ClassReflection $class, string $interface): bool
    {
        return $this->isDispatchableClass($class) && $class->is($interface);
    }

    /**
     * Nested arrays are chains within a batch and are flattened into the same
     * list so each job is inspected once.
     *
     * @return list<array{type: Type, line: int}>
     */
    public function jobItems(Array_ $array, Scope $scope): array
    {
        $items = [];

        foreach ($array->items as $item) {
            if ($item->value instanceof Array_) {
                foreach ($this->jobItems($item->value, $scope) as $nested) {
                    $items[] = $nested;
                }

                continue;
            }

            $items[] = [
                'type' => $scope->getType($item->value),
                'line' => $item->value->getStartLine(),
            ];
        }

        return $items;
    }
}
