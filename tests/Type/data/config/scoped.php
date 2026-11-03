<?php

namespace ConfigScope;

use App\User as ImportedUser;

use function strlen as size;

$value = 42;
$factory = fn () => 'not the config return';

return [
    'local' => $value,
    'imported' => new ImportedUser(),
    'length' => size('config'),
    'file' => __FILE__,
    'unknown' => $notDefined,
];
