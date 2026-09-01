<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use function rescue;

final class FacadeHelper
{
    /** @var array<class-string, object|false> */
    private array $roots = [];

    /** @param class-string $facade */
    public function getRoot(string $facade): object|null
    {
        /** @phpstan-ignore argument.templateType (the caller verifies that the class is a facade) */
        $root = $this->roots[$facade] ??= rescue(static fn () => $facade::getFacadeRoot() ?? false, false, report: false);

        return $root === false ? null : $root;
    }
}
