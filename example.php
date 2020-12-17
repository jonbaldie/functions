<?php

use function Functions\decrypt;
use function Functions\encrypt;
use function Functions\encryption_key;
use function Functions\expose_cookie;
use function Functions\expose_env;
use function Functions\join_file_folder_and_name;
use function Functions\session_begin;

require __DIR__ . '/vendor/autoload.php';

encryption_key(__DIR__);

session_begin();

echo Functions\route([
    'GET' => [
        'foo/bar' => function () {
            return '<h1>foo bar indeed!</h1>';
        },
        'test' => function () {
            return '<h1>it works!</h1>';
        },
        'query' => function (array $all) {
            return '<h1>query string works! see?</h1><pre>' . print_r($all['get'], true) . '</pre>';
        },
        'session' => function () {
            return '<h1>sessions work!</h1><pre>' . print_r(session_id(), true) . '</pre>';
        },
        'cookie' => function () {
            return '<h1>sessions work!</h1><pre>' . print_r(expose_cookie(), true) . '</pre>';
        },
        'environment' => function () {
            return '<h1>sessions work!</h1><pre>' . print_r(expose_env(), true) . '</pre>';
        },
        'generate-key' => function () {
            $key = sodium_crypto_secretbox_keygen();
            $file = join_file_folder_and_name(__DIR__, '/.key');

            file_put_contents($file, $key);

            return '<h1>done!</h1>';
        },
        'encrypt' => function () {
            return encrypt('foo', getenv('ENCRYPTION_KEY'));
        },
        'decrypt' => function (array $all) {
            return decrypt(urldecode($all['get']['encrypted']), getenv('ENCRYPTION_KEY'));
        },
        'session/regenerate' => function () {
            session_regenerate_id();
            
            return '<h1>regenerating session works!</h1><pre>' . print_r(session_id(), true) . '</pre>';
        },
        '/' => function () {
            return '<h1>base url works!</h1>';
        },
    ]
], Functions\expose_all());