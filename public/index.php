<?php

require __DIR__ . '/../vendor/autoload.php';

Functions\encryption_key(__DIR__ . '/../');

Functions\session_begin();

echo Functions\route([
    'GET' => [
        '/' => function (array $all) {
            return $all['view']('index.html');
        },
    ]
], Functions\expose_all());