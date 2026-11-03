<?php

$shared = config('scoped.local');

return [
    'local' => $shared,
    'another' => $shared,
    'expression' => config('scoped.local'),
];
