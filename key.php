<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * Disable output by passing in --silent as an option.
 */
$silent = in_array('--silent', Functions\expose_argv());

/**
 * Check if a .key file already exists.
 */
$file = Functions\join_file_folder_and_name(__DIR__, '/.key');

/**
 * If it already exists, warn the user.
 */
if (file_exists($file)) {
    exit($silent ? '' : "The '.key' file already exists inside this directory. There is rarely a need to replace your app's encryption key. But if you're sure you want to generate a new one, please delete it first.\n");
}

/**
 * Generate a new key using the Sodium engine.
 */
$key = base64_encode(
    sodium_crypto_secretbox_keygen()
);

/**
 * Write the key to the file.
 */
file_put_contents($file, $key);

/**
 * Give the .key file the appropriate permissions.
 */
chmod($file, 0666);

/**
 * Inform the user that the process is complete.
 */
exit($silent ? '' : "Your key is now located in the file '.key' inside this directory. *DO NOT EDIT IT!*\n");
