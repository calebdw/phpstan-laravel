<?php

/** @return array{driver: 'redis'|'sync', retries: positive-int} */
return [
    'driver' => 'sync',
    'retries' => 3,
];
