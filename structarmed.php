<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Preset\Presets\Psr4Preset;

return Architecture::define()
    ->skip([
        Psr4Preset::CLASSES_MUST_MATCH_COMPOSER => [
            __DIR__ . '/tests/application/database/migrations',
        ],
        'source.must_be_final' => [
            __DIR__ . '/tests/application',
        ],
    ])
    ->withPreset(Preset::PSR4())

    ->rule(
        'source.must_be_final',
        new \Boundwize\StructArmed\Rule\Rules\Class_\MustBeFinalRule('Source')
    )

    ->layer('Collectors', 'src/Collectors/')
    ->layer('Ignore', 'src/Ignore/')
    ->layer('Methods', 'src/Methods/')
    ->layer('Parameters', 'src/Parameters/')
    ->layer('PhpDoc', 'src/PhpDoc/')
    ->layer('Properties', 'src/Properties/')
    ->layer('Reflection', 'src/Reflection/')
    ->layer('ResultCache', 'src/ResultCache/')
    ->layer('ReturnTypes', 'src/ReturnTypes/')
    ->layer('Rules', 'src/Rules/')
    ->layer('Schema', 'src/Schema/')
    ->layer('Support', 'src/Support/')
    ->layer('Types', 'src/Types/')
    ->ruleset([
        'Collectors'  => ['+Support'],
        'Ignore'      => ['+Support'],
        'Methods'     => ['+Support'],
        'Parameters'  => ['+Support'],
        'PhpDoc'      => ['+Types'],
        'Properties'  => ['+Support'],
        'Reflection'  => [],
        'ResultCache' => ['Schema'],
        'ReturnTypes' => ['+Parameters', '+Types'],
        'Rules'       => ['+Collectors', '+Properties'],
        'Schema'      => ['Support'],
        'Support'     => ['Methods', 'Reflection', 'Schema'],
        'Types'       => ['+Support'],
    ]);
