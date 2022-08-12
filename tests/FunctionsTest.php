<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function Functions\bind_encryption_key;
use function Functions\csrf_create;
use function Functions\csrf_exists;
use function Functions\csrf_get;
use function Functions\decrypt;
use function Functions\encrypt;
use function Functions\explode_string_by;
use function Functions\expose_all;
use function Functions\env;
use function Functions\fetch_config_files;
use function Functions\generate_random;
use function Functions\get_encryption_key;
use function Functions\has_encryption_key;
use function Functions\join_file_folder_and_name;
use function Functions\list_php_files_in_path;
use function Functions\match_request_to_route;
use function Functions\mod_rewrite;
use function Functions\not_found_route;
use function Functions\response;
use function Functions\route;
use function Functions\save_env;
use function Functions\strip_protocol;
use function Functions\strings_identically_equal;
use function Functions\uri;
use function Functions\url_matches_route;

class FunctionsTest extends TestCase
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
        $this->assertEquals(['post', 'get', 'request', 'server', 'argv', 'env', 'cookie', 'session', 'view', 'config'], array_keys(expose_all()));
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
        $all = [
            'server' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/bar?foo',
                'SERVER_PROTOCOL' => 'HTTP/1.3',
            ],
        ];

        $result = route([
            'GET' => [
                '/foo' => function () {
                    return 'bar';
                },
            ],
        ], $all);

        $this->assertSame($result, not_found_route()($all));
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
        $contents = $this->getDotKeyFileContents();

        $this->assertEquals($contents, get_encryption_key(__DIR__ . '/../'));
    }

    public function testBindEncryptionKey()
    {
        $contents = $this->getDotKeyFileContents();

        bind_encryption_key($contents);

        $this->assertEquals($contents, env('ENCRYPTION_KEY'));
    }

    public function testHasEncryptionKey()
    {
        bind_encryption_key($this->getDotKeyFileContents());

        $this->assertTrue(has_encryption_key());
    }

    public function testEncryption()
    {
        bind_encryption_key($this->getDotKeyFileContents());

        $this->assertIsString(encrypt('foo', env('ENCRYPTION_KEY')));
    }

    public function testDecryption()
    {
        bind_encryption_key($this->getDotKeyFileContents());

        $encrypted = encrypt('foo', $key = env('ENCRYPTION_KEY'));

        $this->assertEquals('foo', decrypt($encrypted, $key));
    }

    public function testCreateCsrf()
    {
        bind_encryption_key($this->getDotKeyFileContents());

        $csrf = csrf_create(env('ENCRYPTION_KEY'));

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

    public function testListingPhpFiles()
    {
        $configs = list_php_files_in_path(__DIR__ . '/../config/');

        $test = array_filter($configs, function (string $config): bool {
            return $config !== strtr($config, '.php', '');
        });

        $this->assertNotEmpty($configs);
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

    public function testIdenticallyEquals()
    {
        $this->assertTrue(strings_identically_equal('foo', 'foo'));
    }

    public function testNotIdenticallyEqual()
    {
        $this->assertFalse(strings_identically_equal('foo', 'bar'));
    }

    public function testModRewrite()
    {
        $this->assertTrue(mod_rewrite('index.php', __DIR__ . '/../public', 'cli-server'));
    }

    public function testModRewriteFalse()
    {
        $this->assertFalse(mod_rewrite('foo.txt', __DIR__ . '/../public', 'foo-server'));
    }

    public function testSaveToEnv()
    {
        save_env('foo', 'bar');

        $this->assertEquals('bar', env('foo'));
    }

    /**
     * @return false|string
     */
    protected function getDotKeyFileContents()
    {
        return get_encryption_key(__DIR__ . '/../');
    }
}
