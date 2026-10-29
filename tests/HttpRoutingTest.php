<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class HttpRoutingTest extends TestCase
{
    /**
     * @dataProvider variableOrders
     */
    public function testWelcomeUsesWorktreeKeyForCsrfCookie(string $order, bool $encoded): void
    {
        $directory = sys_get_temp_dir() . '/functions-http-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $log = $directory . '/server.log';
        $server = null;
        $key = \Functions\get_encryption_key(__DIR__ . '/../');
        do {
            $decoy = bin2hex(random_bytes(intdiv(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 2)));
        } while (hash_equals($key, $decoy));

        try {
            $environment = getenv();
            $environment['ENCRYPTION_KEY'] = $encoded ? base64_encode($decoy) : $decoy;
            $server = proc_open([
                PHP_BINARY,
                '-d', 'variables_order=' . $order,
                '-d', 'session.save_path=' . $directory,
                '-d', 'display_errors=1',
                '-d', 'log_errors=1',
                '-S', '127.0.0.1:0',
                '-t', __DIR__ . '/../public',
                __DIR__ . '/../public/index.php',
            ], [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'a'],
                2 => ['file', $log, 'a'],
            ], $pipes, null, $environment);
            $this->assertIsResource($server);
            fclose($pipes[0]);

            $address = [];
            for ($attempt = 0; $attempt < 100; $attempt++) {
                if (preg_match('~http://127\.0\.0\.1:\d+~', file_get_contents($log), $address)) {
                    break;
                }
                usleep(10000);
            }
            $this->assertNotEmpty($address, 'The PHP server did not start.');

            $context = stream_context_create([
                'http' => ['ignore_errors' => true, 'timeout' => 5],
            ]);
            $body = file_get_contents($address[0] . '/', false, $context);
            $this->assertSame('HTTP/1.1 200 OK', $http_response_header[0]);
            $this->assertTrue(is_string($body) && str_contains($body, 'It works!'), 'Welcome page missing.');
            $this->assertSame(0, preg_match('/(?:warning|notice|fatal error|uncaught|exception|deprecated)/i', $body));
            $this->assertSame(0, preg_match('/(?:warning|notice|fatal error|uncaught|exception|deprecated)/i', file_get_contents($log)));

            $cookie = null;
            foreach ($http_response_header as $header) {
                if (preg_match('/^Set-Cookie:\s*xsrf-token=([^;]*)/i', $header, $match)) {
                    $cookie = rawurldecode($match[1]);
                }
            }
            $this->assertIsString($cookie, 'CSRF cookie missing.');
            $plaintext = \Functions\decrypt($cookie, $key);
            $this->assertTrue(is_string($plaintext) && preg_match('/\A[0-9a-f]{32}\z/', $plaintext) === 1, 'CSRF cookie did not authenticate with the worktree key.');
            $this->assertTrue(\Functions\decrypt($cookie, $decoy) === null, 'CSRF cookie authenticated with the decoy.');
        } finally {
            if (is_resource($server)) {
                proc_terminate($server);
                proc_close($server);
            }
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function variableOrders(): array
    {
        return [['GPCS', true], ['EGPCS', true], ['GPCS', false], ['EGPCS', false]];
    }

    public function testUnmatchedRouteReturnsHttp404(): void
    {
        $directory = sys_get_temp_dir() . '/functions-http-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $log = $directory . '/server.log';
        $server = null;

        try {
            $server = proc_open([
                PHP_BINARY,
                '-d', 'session.save_path=' . $directory,
                '-d', 'display_errors=1',
                '-S', '127.0.0.1:0',
                '-t', __DIR__ . '/../public',
                __DIR__ . '/../public/index.php',
            ], [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'a'],
                2 => ['file', $log, 'a'],
            ], $pipes);
            $this->assertIsResource($server);
            fclose($pipes[0]);

            $address = [];
            for ($attempt = 0; $attempt < 100; $attempt++) {
                if (preg_match('~http://127\.0\.0\.1:\d+~', file_get_contents($log), $address)) {
                    break;
                }
                usleep(10000);
            }
            $this->assertNotEmpty($address, file_get_contents($log));

            $context = stream_context_create([
                'http' => ['ignore_errors' => true, 'timeout' => 5],
            ]);
            $body = file_get_contents($address[0] . '/nope', false, $context);
            $this->assertSame('HTTP/1.1 404 Not Found', $http_response_header[0]);
            $this->assertSame('404 Not Found', $body);

            $body = file_get_contents($address[0] . '//foo', false, $context);
            $this->assertSame('HTTP/1.1 404 Not Found', $http_response_header[0]);
            $this->assertSame('404 Not Found', $body);

            foreach (['/index.php', '/../composer.json', '/%2e%2e/composer.json', '/%00'] as $path) {
                $socket = stream_socket_client(str_replace('http://', 'tcp://', $address[0]));
                fwrite($socket, "GET {$path} HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
                $response = stream_get_contents($socket);
                fclose($socket);
                [$headers, $body] = explode("\r\n\r\n", $response, 2);
                $this->assertStringStartsWith('HTTP/1.1 404 Not Found', $headers, $path);
                $this->assertSame('404 Not Found', $body, $path);
            }

            $body = file_get_contents($address[0] . '/robots.txt', false, $context);
            $this->assertSame('HTTP/1.1 200 OK', $http_response_header[0]);
            $this->assertSame(file_get_contents(__DIR__ . '/../public/robots.txt'), $body);

            $body = file_get_contents($address[0] . '/', false, $context);
            $this->assertSame('HTTP/1.1 200 OK', $http_response_header[0]);
            $this->assertStringContainsString('It works!', $body);

            $responses = [];
            foreach (['POST', 'HEAD'] as $method) {
                $context = stream_context_create([
                    'http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 5],
                ]);
                $body = file_get_contents($address[0] . '/', false, $context);
                $responses[$method] = [$http_response_header[0], $body];
            }
            $this->assertSame([
                'POST' => ['HTTP/1.1 404 Not Found', '404 Not Found'],
                'HEAD' => ['HTTP/1.1 404 Not Found', ''],
            ], $responses);
        } finally {
            if (is_resource($server)) {
                proc_terminate($server);
                proc_close($server);
            }
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
