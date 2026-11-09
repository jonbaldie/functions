<?php

if (DIRECTORY_SEPARATOR !== '/') {
    fwrite(STDERR, "This test requires POSIX permissions.\n");
    exit(1);
}

$root = sys_get_temp_dir() . '/functions-key-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
mkdir($root . '/vendor');
copy(__DIR__ . '/../key.php', $root . '/key.php');
file_put_contents($root . '/vendor/autoload.php', '<?php require ' . var_export(realpath(__DIR__ . '/../src/functions.php'), true) . ';');

function runKey(string $root, int $mask, bool $silent = false): array
{
    $program = 'umask(' . $mask . '); require ' . var_export($root . '/key.php', true) . ';';
    $command = [PHP_BINARY, '-r', $program];
    if ($silent) {
        $command[] = '--';
        $command[] = '--silent';
    }
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    foreach ([0000, 0077] as $mask) {
        [$status] = runKey($root, $mask);
        clearstatcache(true, $root . '/.key');
        $mode = fileperms($root . '/.key') & 0777;
        $key = file_get_contents($root . '/.key');
        check($status === 0, 'Key generation failed');
        check($mode === 0600, sprintf('Expected mode 600 under umask %03o, got %03o', $mask, $mode));
        check(strlen(base64_decode($key, true)) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'Invalid key');
        chmod($root . '/.key', 0640);
        [$status] = runKey($root, $mask);
        clearstatcache(true, $root . '/.key');
        check($status === 0 && file_get_contents($root . '/.key') === $key, 'Rerun changed key');
        check((fileperms($root . '/.key') & 0777) === 0640, 'Rerun changed permissions');
        unlink($root . '/.key');
    }
    if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
        chmod($root, 0500);
        [$status, $stdout] = runKey($root, 0000, true);
        chmod($root, 0700);
        check($status !== 0, 'Unwritable directory reported success');
        check($stdout === '', 'Silent failure wrote success output');
        check(!file_exists($root . '/.key'), 'Failure left a key');
    }
    echo "Key generation checks passed.\n";
} finally {
    chmod($root, 0700);
    foreach (glob($root . '/.*') as $file) {
        if (is_file($file) || is_link($file)) {
            unlink($file);
        }
    }
    unlink($root . '/vendor/autoload.php');
    rmdir($root . '/vendor');
    unlink($root . '/key.php');
    rmdir($root);
}
