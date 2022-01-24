<?php

namespace Functions;

use Closure;
use PDO;
use function array_key_exists;
use function base64_decode;
use function base64_encode;
use function basename;
use function bin2hex;
use function explode;
use function file_get_contents;
use function glob;
use function header;
use function is_callable;
use function mb_substr;
use function openssl_random_pseudo_bytes;
use function parse_url;
use function random_bytes;
use function rtrim;
use function setcookie;
use function strtr;
use function time;
use function trim;
use function urldecode;

/**
 * @param string $string
 * @return string
 */
function strip_protocol(string $string): string
{
    return strtr($string, [
        'https://' => '',
        'http://' => '',
    ]);
}

/**
 * @param string $delimiter
 * @return Closure
 */
function explode_string_by(string $delimiter): Closure
{
    return function (string $string) use ($delimiter): array
    {
        return explode($delimiter, $string);
    };
}

/**
 * @return array
 */
function expose_post(): array
{
    return $_POST;
}

/**
 * @return array
 */
function expose_get(): array
{
    return $_GET;
}

/**
 * @return array
 */
function expose_request(): array
{
    return $_REQUEST;
}

/**
 * @return array
 */
function expose_server(): array
{
    return $_SERVER;
}

/**
 * @return array
 */
function expose_argv(): array
{
    return expose_server()['argv'] ?? [];
}

/**
 * @return array
 */
function expose_env(): array
{
    return $_ENV;
}

/**
 * @param string $key
 * @param mixed $value
 * @return void
 */
function save_env(string $key, $value): void
{
    $_ENV[$key] = $value;
}

/**
 * @param string $key
 * @return mixed
 */
function env(string $key)
{
    return expose_env()[$key];
}

/**
 * @return array
 */
function expose_all(): array
{
    return [
        'post' => expose_post(),
        'get' => expose_get(),
        'request' => expose_request(),
        'server' => expose_server(),
        'argv' => expose_argv(),
        'env' => expose_env(),
        'cookie' => expose_cookie(),
        'session' => expose_session(),
        'view' => view(__DIR__ . '/../views'),
    ];
}

/**
 * @param string $request_uri
 * @return string
 */
function uri(string $request_uri): string
{
    return urldecode(parse_url($request_uri, PHP_URL_PATH));
}

/**
 * @param string $uri
 * @param string $public_path
 * @param string $php_sapi
 * @return bool
 */
function mod_rewrite(string $uri, string $public_path, string $php_sapi): bool
{
    return $php_sapi === 'cli-server'
        && $uri !== '/' 
        && file_exists(join_file_folder_and_name($public_path, $uri));
}

/**
 * @param string $uri
 * @param string $route_pattern
 * @return boolean
 */
function url_matches_route(string $uri, string $route_pattern): bool
{
    $exploded = explode_string_by('?')($uri);
    $uri = trim($exploded[0], '/');
    $route_pattern = trim($route_pattern, '/');

    return "/{$uri}" === "/{$route_pattern}";
}

/**
 * @param array $routes
 * @return Closure
 */
function match_request_to_route(array $routes): Closure
{
    return function (string $request_method, string $url) use ($routes): ?callable {
        $routes_to_use = $routes[$request_method] ?? null;

        if ($routes_to_use === null) {
            return null;
        }

        foreach ($routes_to_use as $route_pattern => $callable) {
            if (url_matches_route($url, $route_pattern)) {
                return $callable;
            }
        }

        return null;
    };
}

/**
 * @param array $routes
 * @param array $exposed_all
 * @return mixed
 */
function route(array $routes, array $exposed_all)
{
    $callable = match_request_to_route($routes)(
        $exposed_all['server']['REQUEST_METHOD'],
        $exposed_all['server']['REQUEST_URI']
    );

    if (is_callable($callable)) {
        return $callable($exposed_all);
    }
}

/**
 * @param string|null $response
 * @param array $server
 * @return string
 */
function response(?string $response, array $server): string
{
    if ($response === null) {
        header($server['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);

        return '404 Not Found';
    }

    return $response;
}

/**
 * @param string $path
 * @return array
 */
function list_php_files_in_path(string $path): array
{
    return glob(join_file_folder_and_name($path, '/*.php'));
}

/**
 * @param string $path
 * @return array
 */
function fetch_config_files(string $path): array
{
    $kv = array_map(function (string $config_file) {
        $config = require $config_file;

        return [
            'key' => strtr(basename($config_file), ['.php' => '']),
            'value' => $config, 
        ];
    }, list_php_files_in_path($path));

    return array_combine(
        array_column($kv, 'key'),
        array_column($kv, 'value'),
    );
}

/**
 * @param string $dsn
 * @param string|null $username
 * @param string|null $password
 * @return PDO
 */
function pdo(string $dsn, ?string $username = null, ?string $password = null): PDO
{
    return new PDO($dsn, $username, $password);
}

/**
 * @param string $path_to_db_file
 * @return PDO
 */
function sqlite(string $path_to_db_file): PDO
{
    return pdo("sqlite:{$path_to_db_file}");
}

/**
 * @param string $host
 * @param string $db_name
 * @param string $username
 * @param string $password
 * @return PDO
 */
function mysql(string $host, string $db_name, string $username, string $password): PDO
{
    return pdo("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password);
}

/**
 * @param string $folder
 * @param string $filename
 * @return string
 */
function join_file_folder_and_name(string $folder, string $filename): string
{
    return rtrim($folder, '/') . '/' . trim($filename, '/');
}

/**
 * @param string $view_folder
 * @return Closure
 */
function view(string $view_folder): Closure
{
    return function (string $view_name, array $kv_replacements = []) use ($view_folder) {
        $contents = file_get_contents(join_file_folder_and_name($view_folder, $view_name));

        return strtr($contents, $kv_replacements);
    };
}

/**
 * @param integer $length
 * @return string
 */
function generate_random(int $length): string
{
    return bin2hex(openssl_random_pseudo_bytes($length));
}

/**
 * It's important that sessions are strictly read-only.
 * 
 * @param integer $lifetime
 * @return boolean
 */
function session_begin(int $lifetime = 86400): bool
{
    return session_start([
        'cookie_lifetime' => $lifetime,
    ]);
}

/**
 * @param string $key
 * @return void
 */
function bind_encryption_key(string $key): void
{
    save_env('ENCRYPTION_KEY', $key);
}

function csrf_exists(): bool
{
    return array_key_exists('xsrf-token', expose_cookie());
}

/**
 * @return string|null
 */
function csrf_get(): ?string
{
    return expose_cookie()['xsrf-token'] ?? null;
}

/**
 * @param string $key
 * @return string
 */
function csrf_create(string $key): string
{
    return encrypt(generate_random(16), $key);
}

/**
 * @param string $csrf
 * @param integer $seconds
 * @return void
 */
function csrf_send(string $csrf, int $seconds = 3600): void
{
    setcookie('xsrf-token', $csrf, time() + $seconds);
}

/**
 * @return boolean
 */
function has_encryption_key(): bool
{
    return empty(env('ENCRYPTION_KEY')) === false;
}

/**
 * @param string $string
 * @param string $key
 * @return string
 */
function encrypt(string $string, string $key): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $secret = sodium_crypto_secretbox($string, $nonce, $key);

    return base64_encode($nonce . $secret);
}

/**
 * @param string $path
 * @return string
 */
function get_encryption_key(string $path): string
{
    return base64_decode(file_get_contents(join_file_folder_and_name($path, '/.key')));
}

/**
 * @param string $encoded
 * @param string $key
 * @return string
 */
function decrypt(string $encoded, string $key): string
{
    $decoded = base64_decode($encoded);
    $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

    return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
}

/**
 * @return array
 */
function expose_cookie(): array
{
    return $_COOKIE ?? [];
}

/**
 * @return array
 */
function expose_session(): array
{
    return $_SESSION ?? [];
}
