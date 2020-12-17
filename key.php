<?php

require __DIR__ . '/vendor/autoload.php';

$file = Functions\join_file_folder_and_name(__DIR__, '/.key');

if (file_exists($file)) {
    exit("The '.key' file already exists inside this directory. There is rarely a need to replace your app's encryption key. But if you're sure you want to generate a new one, please delete it first.");
}

$key = sodium_crypto_secretbox_keygen();

file_put_contents($file, $key);

exit("Your key is now located in the file '.key' inside this directory. *DO NOT EDIT IT* - We recommend running 'chmod 0600 .key' to make certain it remains read-only.");
