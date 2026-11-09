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
    exit($silent === true ? '' : "The '.key' file already exists inside this directory. There is rarely a need to replace your app's encryption key. But if you're sure you want to generate a new one, please delete it first.\n");
}

/**
 * Generate a new key using the Sodium engine.
 */
$key = base64_encode(
    sodium_crypto_secretbox_keygen()
);

$temporary = @tempnam(__DIR__, '.key-');
if ($temporary === false) {
    fwrite(STDERR, "Could not create a private temporary key file.\n");
    exit(1);
}

$written = false;
try {
    $permissions = @fileperms($temporary);
    if (dirname($temporary) === __DIR__ && $permissions !== false && ($permissions & 0077) === 0) {
        $written = @file_put_contents($temporary, $key) === strlen($key)
            && @link($temporary, $file);
    }
} finally {
    @unlink($temporary);
}

if (!$written) {
    fwrite(STDERR, "Could not write the key to '.key'. No existing key was changed.\n");
    exit(1);
}

/**
 * Inform the user that the process is complete.
 */
exit($silent === true ? '' : "Your key is now located in the file '.key' inside this directory. *DO NOT EDIT IT!*\n");
