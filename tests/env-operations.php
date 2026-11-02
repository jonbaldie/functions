<?php

declare(strict_types=1);

namespace Functions;

$lookups = [];

function getenv(...$arguments)
{
    global $lookups;
    $lookups[] = $arguments;
    return \getenv(...$arguments);
}

require __DIR__ . '/../src/functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

for ($i = 0; $i < 200; $i++) {
    putenv("FUNCTIONS_SCALE_$i=value-$i");
    $_ENV["FUNCTIONS_SCALE_$i"] = "override-$i";
}
$_ENV['FUNCTIONS_SCALE_0'] = null;
$_ENV['FUNCTIONS_SCALE_1'] = false;
unset($_ENV['FUNCTIONS_SCALE_199']);
$lookups = [];
$snapshot = expose_env();
check($snapshot['FUNCTIONS_SCALE_0'] === null, 'Snapshot lost null override');
check($snapshot['FUNCTIONS_SCALE_1'] === false, 'Snapshot lost false override');
check($snapshot['FUNCTIONS_SCALE_198'] === 'override-198', 'Snapshot lost override');
check($snapshot['FUNCTIONS_SCALE_199'] === 'value-199', 'Snapshot lost process value');
$scaleLookups = array_values(array_filter($lookups, static function ($arguments) {
    return isset($arguments[0]) && strpos((string) $arguments[0], 'FUNCTIONS_SCALE_') === 0;
}));
check($scaleLookups === [['FUNCTIONS_SCALE_199', true]], 'Snapshot looked up overridden keys');

$lookups = [];
check(env('FUNCTIONS_SCALE_199') === 'value-199', 'Single lookup lost process value');
check(count($lookups) <= 2, 'Single lookup performed per-key lookups');
putenv('FUNCTIONS_SCALE_199=changed');
check(env('FUNCTIONS_SCALE_199') === 'changed', 'Single lookup did not observe putenv');
check(expose_env()['FUNCTIONS_SCALE_199'] === 'changed', 'Snapshot did not observe putenv');
foreach ([false, null, '', 0] as $value) {
    $_ENV['FUNCTIONS_SCALE_199'] = $value;
    check(env('FUNCTIONS_SCALE_199') === $value, 'Single lookup lost override');
    check(expose_env()['FUNCTIONS_SCALE_199'] === $value, 'Snapshot lost override');
}
unset($_ENV['FUNCTIONS_SCALE_199']);

foreach (["FUNCTIONS_SCALE_199\0suffix", 'FUNCTIONS_SCALE_199.missing', 'FUNCTIONS_SCALE_MISSING'] as $key) {
    $warnings = [];
    set_error_handler(function ($severity, $message) use (&$warnings) {
        $warnings[] = $message;
        return true;
    });
    try {
        check(env($key) === null, 'Missing key must return null');
    } finally {
        restore_error_handler();
    }
    check(count($warnings) === 1 && strpos($warnings[0], 'Undefined array key') !== false, 'Missing key must issue a warning');
}

foreach (['FUNCTIONS.TEST_DOT=dot', '12345=numeric', 'FUNCTIONS_Ü=unicode'] as $entry) {
    putenv($entry);
}
foreach (['FUNCTIONS.TEST_DOT', 'FUNCTIONS_TEST_DOT', '12345', 'FUNCTIONS_Ü', ''] as $key) {
    $local = [];
    foreach (array_keys(\getenv()) as $name) {
        $local[$name] = \getenv((string) $name, true);
    }
    $reference = $_ENV + array_filter($local, 'is_string');
    set_error_handler(static function () { return true; });
    try {
        check(env($key) === $reference[$key], 'Unusual key differs from snapshot');
    } finally {
        restore_error_handler();
    }
}

echo "Environment semantics and lookup scaling: PASS\n";
