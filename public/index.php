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
Functions\has_encryption_key() || Functions\bind_encryption_key(
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
    Functions\csrf_create(getenv('ENCRYPTION_KEY'))
);

/**
 * If we're in a cli-server, then emulate mod_rewrite.
 */
$mod_rewrite = Functions\mod_rewrite(
    Functions\uri(Functions\expose_server()['REQUEST_URI']),
    __DIR__ . '/../public',
    PHP_SAPI
);

if ($mod_rewrite) {
    return false;
}

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