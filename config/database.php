<?php

return [
    'mysql' => [
        'host' => getenv('DB_HOST'),
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
        'name' => getenv('DB_NAME'),
    ],

    'sqlite' => [
        'path' => getenv('DB_PATH'),
    ],
];
