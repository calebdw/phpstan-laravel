<?php

// Laravel sets `override` before `override.nested`, so this file is what the
// key ends up holding, not the `nested` entry in config/override.php.
return [
    'from' => 'child',
];
