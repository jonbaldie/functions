<?php

require __DIR__ . '/vendor/autoload.php';

$silent = in_array('--silent', Functions\expose_argv());

$file = Functions\join_file_folder_and_name(__DIR__, '/.key');

if (file_exists($file)) {
    exit($silent ? '' : "The '.key' file already exists inside this directory. There is rarely a need to replace your app's encryption key. But if you're sure you want to generate a new one, please delete it first.\n");
}

$key = base64_encode(
    $gen = sodium_crypto_secretbox_keygen()
);

file_put_contents($file, $key);

chmod($file, 0600);

exit($silent ? '' : "Your key is now located in the file '.key' inside this directory. *DO NOT EDIT IT!*\n");
