<?php

return [
    'string' => 'value',
    'int' => 1,
    'float' => 1.5,
    'bool' => true,
    'null' => null,
    'env' => env('PACKAGE_ENV', 'file'),
    'nested' => [
        'key' => 'value',
        'list' => [1, 2, 3],
        'deep' => [
            'key' => 'value',
        ],
    ],
];
