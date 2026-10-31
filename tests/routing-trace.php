<?php

declare(strict_types=1);

if (!extension_loaded('xdebug') || !function_exists('xdebug_info') || !in_array('trace', xdebug_info('mode'), true)) {
    fwrite(STDERR, "Xdebug with trace mode enabled is required.\n");
    exit(1);
}
if ($argc > 2 || !is_file($argv[1] ?? __DIR__ . '/../src/functions.php')) {
    fwrite(STDERR, "Usage: php tests/routing-trace.php [source-file]\n");
    exit(1);
}
require $argv[1] ?? __DIR__ . '/../src/functions.php';

/** @return array{0: callable, 1: array{explode: int, trim: int, callbacks: int}} */
function trace_match(callable $match, string $url): array
{
    $base = tempnam(sys_get_temp_dir(), 'routing-trace-');
    if ($base === false) {
        throw new RuntimeException('Cannot create temporary trace file');
    }
    $filename = false;
    try {
        $filename = xdebug_start_trace($base, XDEBUG_TRACE_COMPUTERIZED | XDEBUG_TRACE_NAKED_FILENAME);
        if (!is_string($filename)) {
            throw new RuntimeException('Cannot start Xdebug trace');
        }
        try {
            $handler = $match('GET', $url);
        } finally {
            $stopped = xdebug_stop_trace();
        }
        if ($stopped !== $filename) {
            throw new RuntimeException('Cannot stop Xdebug trace');
        }
        $trace = file_get_contents($filename);
        if ($trace === false || $trace === '') {
            throw new RuntimeException('Trace is empty or unreadable');
        }
        if (substr($trace, 0, 2) === "\x1f\x8b") {
            $trace = gzdecode($trace);
            if ($trace === false) {
                throw new RuntimeException('Cannot decompress trace');
            }
        }
        if (!preg_match('/^Version: .+\RFile format: 4\RTRACE START \[.+\]\R/', $trace)
            || !preg_match('/TRACE END\s+\[.+\]\R\s*$/', $trace)) {
            throw new RuntimeException('Trace markers or format are missing');
        }
        $stack = [];
        $seen = [];
        $counts = ['explode' => 0, 'trim' => 0, 'callbacks' => 0];
        $matcherEntries = 0;
        $matcherExits = 0;
        foreach (preg_split('/\R/', $trace) as $line) {
            if (!preg_match('/^\d+\t\d+\t[01]\t/', $line)) {
                continue;
            }
            $fields = explode("\t", $line);
            [$level, $id, $event] = array_map('intval', array_slice($fields, 0, 3));
            if ($event === 0) {
                $name = $fields[5] ?? '';
                if ($name === 'xdebug_stop_trace') {
                    if ($stack !== []) {
                        throw new RuntimeException('Trace stopped with open matcher frames');
                    }
                    continue;
                }
                if ($name === '' || isset($seen[$id]) || ($stack && $level !== end($stack)['level'] + 1)) {
                    throw new RuntimeException('Invalid trace entry');
                }
                $parent = $stack ? end($stack)['name'] : null;
                if (!$stack) {
                    if (strpos($name, '{closure:') === false) {
                        throw new RuntimeException('Trace does not start with matcher closure');
                    }
                    $matcherEntries++;
                }
                if ($name === 'explode' || $name === 'trim') {
                    $counts[$name]++;
                }
                if ($parent === 'array_reduce' && strpos($name, '{closure:') !== false) {
                    $counts['callbacks']++;
                }
                $seen[$id] = true;
                $stack[] = ['id' => $id, 'level' => $level, 'name' => $name];
            } else {
                $frame = array_pop($stack);
                if ($frame === null || $frame['id'] !== $id || $frame['level'] !== $level) {
                    throw new RuntimeException('Unmatched trace exit');
                }
                if (!$stack) {
                    $matcherExits++;
                }
            }
        }
        if ($stack || $matcherEntries !== 1 || $matcherExits !== 1) {
            throw new RuntimeException('Incomplete matcher trace');
        }
        return [$handler, $counts];
    } finally {
        if (is_string($filename) && is_file($filename)) {
            unlink($filename);
        }
        if (is_file($base)) {
            unlink($base);
        }
    }
}

try {
    $first = static fn (array $all): string => 'first';
    $missRoutes = [];
    $hitRoutes = ['/route0' => $first];
    for ($i = 0; $i < 100; $i++) {
        $missRoutes['/route' . $i] = static fn (array $all): string => 'other';
        if ($i > 0) {
            $hitRoutes['/route' . $i] = static fn (array $all): string => 'other';
        }
    }
    $missMatch = Functions\match_request_to_route(['GET' => $missRoutes]);
    $hitMatch = Functions\match_request_to_route(['GET' => $hitRoutes]);
    [$miss, $missCounts] = trace_match($missMatch, '/missing?' . str_repeat('x', 8000));
    [$hit, $hitCounts] = trace_match($hitMatch, '/route0?' . str_repeat('x', 8000));
    $errors = [];
    if ($miss([]) !== '404 Not Found') {
        $errors[] = 'Miss did not return the 404 handler output';
    }
    if ($hit !== $first || $hit([]) !== 'first') {
        $errors[] = 'First hit did not return the first handler';
    }
    printf("Long miss explode calls: %d (expected 1)\n", $missCounts['explode']);
    printf("First hit trim calls: %d (expected 2)\n", $hitCounts['trim']);
    printf("First hit reducer callbacks: %d (expected 0)\n", $hitCounts['callbacks']);
    if ($missCounts['explode'] !== 1) {
        $errors[] = 'Long miss split count differs from 1';
    }
    if ($hitCounts['trim'] !== 2) {
        $errors[] = 'First hit did not stop comparing routes';
    }
    if ($hitCounts['callbacks'] !== 0) {
        $errors[] = 'First hit ran reducer callbacks';
    }
    if ($errors) {
        throw new RuntimeException(implode("\n", $errors));
    }
    echo "Routing trace guard passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
