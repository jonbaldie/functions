<?php

namespace Tests;

use function Functions\bind_encryption_key;
use function Functions\csrf_create;
use function Functions\csrf_exists;
use function Functions\csrf_get;
use function Functions\csrf_send;
use function Functions\decrypt;
use function Functions\encrypt;
use function Functions\encryption_key;
use function Functions\explode_string_by;
use function Functions\expose_all;
use function Functions\fetch_config_files;
use function Functions\generate_random;
use function Functions\get_encryption_key;
use function Functions\has_encryption_key;
use function Functions\interpret_response;
use function Functions\join_file_folder_and_name;
use function Functions\match_request_to_route;
use function Functions\mod_rewrite;
use function Functions\response;
use function Functions\route;
use function Functions\strip_protocol;
use function Functions\uri;
use function Functions\url_matches_route;

class FunctionsTest extends \PHPUnit\Framework\TestCase
{
    public function testStripProtocolHttp()
    {
        $this->assertEquals('example.com', strip_protocol('http://example.com'));
    }

    public function testStripProtocolHttps()
    {
        $this->assertEquals('example.com', strip_protocol('https://example.com'));
    }

    public function testExplodeStringBySlash()
    {
        $this->assertEquals(['foo', 'bar'], explode_string_by('/')('foo/bar'));
    }

    public function testExplodeStringByComma()
    {
        $this->assertEquals(['foo', 'bar'], explode_string_by(',')('foo,bar'));
    }

    public function testExposeAllReadOnlyVariables()
    {
        $this->assertEquals(['post', 'get', 'request', 'server', 'argv', 'env', 'cookie', 'session', 'view'], array_keys(expose_all()));
    }

    public function testUriMatchesRoutePatternBaseUrl()
    {
        $this->assertTrue(url_matches_route('/?foo=bar', '/'));
    }

    public function testUriMatchesRoutePatternWordpressUrls()
    {
        $this->assertTrue(url_matches_route('/post/url/', '/post/url'));
    }

    public function testUriMatchesRoutePatternTrue()
    {
        $this->assertTrue(url_matches_route('/foo/bar?foo=bar', '/foo/bar'));
    }

    public function testUriMatchesRoutePatternFalse()
    {
        $this->assertFalse(url_matches_route('/foo/bar?foo=bar', '/no/way'));
    }

    public function testMatchingRequestToRoute()
    {
        $function = match_request_to_route([
            'GET' => [
                '/foo' => function () {
                    return 'bar';
                },
            ],
        ]);

        $this->assertIsCallable($function('GET', '/foo?bar'));
    }

    public function testMatchingRequestToRouteNull()
    {
        $function = match_request_to_route([
            'GET' => [
                '/foo' => function () {
                    return 'bar';
                },
            ],
        ]);

        $this->assertNull($function('GET', '/bar?foo'));
    }

    public function testRoutingFunction()
    {
        $result = route([
            'GET' => [
                '/foo' => function () {
                    return 'bar';
                },
            ],
        ], [
            'server' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/foo?bar',
            ],
        ]);

        $this->assertEquals('bar', $result);
    }

    public function testRoutingFunctionFails()
    {
        $result = route([
            'GET' => [
                '/foo' => function () {
                    return 'bar';
                },
            ],
        ], [
            'server' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/bar?foo',
            ],
        ]);

        $this->assertNull($result);
    }

    public function testResponseInterpreter()
    {
        $this->assertEquals('bar', response('bar', []));
    }

    public function testJoiningFileFolderAndName()
    {
        $this->assertEquals('/tmp/foo.php', join_file_folder_and_name('/tmp/', '/foo.php'));
    }

    public function testJoiningFileFolderAndNameWithoutSlashes()
    {
        $this->assertEquals('/tmp/foo.php', join_file_folder_and_name('/tmp', 'foo.php'));
    }

    public function testGeneratingRandomStrings()
    {
        $this->assertIsString(generate_random(16));
    }

    public function testGetEncryptionKey()
    {
        $this->assertEquals(file_get_contents(__DIR__ . '/../.key'), get_encryption_key(__DIR__ . '/../'));
    }

    public function testBindEncryptionKey()
    {
        bind_encryption_key(file_get_contents(__DIR__ . '/../.key'));

        $this->assertEquals(file_get_contents(__DIR__ . '/../.key'), getenv('ENCRYPTION_KEY'));
    }

    public function testHasEncryptionKey()
    {
        bind_encryption_key(file_get_contents(__DIR__ . '/../.key'));

        $this->assertTrue(has_encryption_key());
    }

    public function testEncryption()
    {
        bind_encryption_key(file_get_contents(__DIR__ . '/../.key'));

        $this->assertIsString(encrypt('foo', getenv('ENCRYPTION_KEY')));
    }

    public function testDecryption()
    {
        bind_encryption_key(file_get_contents(__DIR__ . '/../.key'));

        $encrypted = encrypt('foo', $key = getenv('ENCRYPTION_KEY'));

        $this->assertEquals('foo', decrypt($encrypted, $key));
    }

    public function testCreateCsrf()
    {
        bind_encryption_key(file_get_contents(__DIR__ . '/../.key'));

        $csrf = csrf_create(getenv('ENCRYPTION_KEY'));

        $this->assertIsString($csrf);
    }

    public function testGetCsrfNull()
    {
        $this->assertNull(csrf_get());
    }

    public function testExistsCsrfFalse()
    {
        $this->assertFalse(csrf_exists());
    }

    public function testBindingConfigs()
    {
        $configs = fetch_config_files(__DIR__ . '/../config/');

        $this->assertArrayHasKey('database', $configs);
    }

    public function testParsedUri()
    {
        $this->assertEquals('foo', uri('foo'));
    }

    public function testModRewrite()
    {
        $this->assertTrue(mod_rewrite('index.php', __DIR__ . '/../public', 'cli-server'));
    }

    public function testModRewriteFalse()
    {
        $this->assertFalse(mod_rewrite('foo.txt', __DIR__ . '/../public', 'foo-server'));
    }
}