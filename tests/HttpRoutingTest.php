<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class HttpRoutingTest extends TestCase
{
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

            foreach (['/index.php', '/../composer.json', '/%2e%2e/composer.json'] as $path) {
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
