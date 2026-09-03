<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;

final class AppMakeHelper
{
    public function __construct(
        private ContainerHelper $containerHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function resolveType(Arg|null $abstract, Scope $scope): Type|null
    {
        if ($abstract === null) {
            return null;
        }

        $abstracts = $this->typeHelper->constantStrings($scope->getType($abstract->value));

        return $abstracts === [] ? null : $this->containerHelper->getType($abstracts);
    }
}
