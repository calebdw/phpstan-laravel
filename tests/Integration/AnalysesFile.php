<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\Error;

trait AnalysesFile
{
    /** @return list<Error> */
    private function analyse(string $file): array
    {
        $analyser = self::getContainer()->getByType(Analyser::class);

        return $analyser->analyse([$file], null, null, true)->getErrors();
    }
}
