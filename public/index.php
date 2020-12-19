<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Bind all configuration options to the session.
 */
define('CONFIG', Functions\fetch_config_files(
    __DIR__ . '/../config'
));

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
 * Work out a response from the HTTP request.
 */
$response = Functions\route([
    'GET' => [
        '/' => function (array $all) {
            return $all['view']('index.html');
        },
    ]
], $all = Functions\expose_all());

/**
 * Now send the response, including any headers.
 */
echo Functions\response($response, $all['server']);