<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Fetch and bind our encryption key to the session.
 */
Functions\bind_encryption_key(
    $key = Functions\get_encryption_key(__DIR__ . '/../')
);

/**
 * Start the session so that the user can log in, etc.
 */
Functions\session_begin();

/**
 * If the CSRF token hasn't been sent, then send it.
 */
Functions\csrf_exists() || Functions\csrf_send(
    Functions\csrf_create($key)
);

/**
 * Work out a response and then send it.
 */
echo Functions\route([
    'GET' => [
        '/' => function (array $all) {
            return $all['view']('index.html');
        },
    ]
], Functions\expose_all());