<?php

declare(strict_types=1);

namespace Functions;

$lookups = [];

function getenv(...$args)
{
    global $lookups;
    $lookups[] = $args;
    return \getenv(...$args);
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
}
unset($_ENV['FUNCTIONS_SCALE_0']);

$lookups = [];
$snapshot = expose_env();
check($snapshot['FUNCTIONS_SCALE_0'] === 'value-0', 'Snapshot lost a process value');
check($snapshot['FUNCTIONS_SCALE_199'] === 'value-199', 'Snapshot lost a later process value');
check(count($lookups) <= 1, 'Snapshot must not look up every environment key');

$lookups = [];
check(env('FUNCTIONS_SCALE_0') === 'value-0', 'Single lookup lost a process value');
check(count($lookups) === 1 && $lookups[0] === ['FUNCTIONS_SCALE_0', true], 'Single lookup must use a local key lookup');
putenv('FUNCTIONS_SCALE_0=changed');
check(env('FUNCTIONS_SCALE_0') === 'changed', 'Single lookup must observe putenv changes');
check(expose_env()['FUNCTIONS_SCALE_0'] === 'changed', 'Snapshot must observe putenv changes');

foreach ([false, null, '', 0] as $value) {
    $_ENV['FUNCTIONS_SCALE_0'] = $value;
    check(env('FUNCTIONS_SCALE_0') === $value, 'Single lookup must preserve saved values');
    check(expose_env()['FUNCTIONS_SCALE_0'] === $value, 'Snapshot must preserve saved values');
}
unset($_ENV['FUNCTIONS_SCALE_0']);
putenv('FUNCTIONS_SCALE_0');
$warnings = [];
set_error_handler(function ($severity, $message) use (&$warnings) {
    $warnings[] = $message;
    return true;
});
try {
    check(env('FUNCTIONS_SCALE_0') === null, 'Missing key must return null');
} finally {
    restore_error_handler();
}
check(count($warnings) === 1 && strpos($warnings[0], 'Undefined array key') !== false, 'Missing key must issue a warning');
check(!array_key_exists('FUNCTIONS_SCALE_0', expose_env()), 'Snapshot must omit missing keys');

echo "Environment semantics and lookup scaling: PASS\n";
