<?php

namespace Tests;

use function Functions\decrypt;
use function Functions\encrypt;
use function Functions\encryption_key;
use function Functions\explode_string_by;
use function Functions\expose_all;
use function Functions\generate_random;
use function Functions\has_encryption_key;
use function Functions\join_file_folder_and_name;
use function Functions\match_request_to_route;
use function Functions\route;
use function Functions\strip_protocol;
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
        $this->assertEquals(['post', 'get', 'request', 'server', 'argv', 'env', 'cookie', 'session'], array_keys(expose_all()));
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

    public function testBindingEncryptionKey()
    {
        encryption_key(__DIR__ . '/../');

        $this->assertEquals(file_get_contents(__DIR__ . '/../.key'), getenv('ENCRYPTION_KEY'));
    }

    public function testHasEncryptionKey()
    {
        encryption_key(__DIR__ . '/../');

        $this->assertTrue(has_encryption_key());
    }

    public function testEncryption()
    {
        encryption_key(__DIR__ . '/../');

        $this->assertIsString(encrypt('foo', getenv('ENCRYPTION_KEY')));
    }

    public function testDecryption()
    {
        encryption_key(__DIR__ . '/../');

        $encrypted = encrypt('foo', $key = getenv('ENCRYPTION_KEY'));

        $this->assertEquals('foo', decrypt($encrypted, $key));
    }


}