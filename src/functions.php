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
 * Strip the HTTP protocol from a given string.
 * 
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
 * Returns a function that can explode strings by
 * a given delimiter.
 * 
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
 * Returns a copy of $_POST.
 * 
 * @return array
 */
function expose_post(): array
{
    return $_POST;
}

/**
 * Returns a copy of $_GET.
 * 
 * @return array
 */
function expose_get(): array
{
    return $_GET;
}

/**
 * Returns a copy of $_REQUEST.
 * 
 * @return array
 */
function expose_request(): array
{
    return $_REQUEST;
}

/**
 * Returns a copy of $_SERVER.
 * 
 * @return array
 */
function expose_server(): array
{
    return $_SERVER;
}

/**
 * Returns a copy of $ARGV.
 * 
 * @return array
 */
function expose_argv(): array
{
    return expose_server()['argv'] ?? [];
}

/**
 * Returns a copy of $_ENV.
 * 
 * @return array
 */
function expose_env(): array
{
    return $_ENV;
}

/**
 * Eventually we will need to write to $_ENV.
 * This is one of the only places we violate the strict
 * laws of functional programming, for practical resaons.
 * 
 * @param string $key
 * @param mixed $value
 * @return void
 */
function save_env(string $key, $value): void
{
    $_ENV[$key] = $value;
}

/**
 * Fetch a specfic environment variable from $_ENV.
 * 
 * @param string $key
 * @return mixed
 */
function env(string $key)
{
    return expose_env()[$key];
}

/**
 * A handy way to summon all the useful "background" variables
 * in an immutable form. Each of the "expose_*" functions returns
 * a _copy_ of the underlying data to avoid accidental edits.
 * 
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
 * Return a useful URI string from the given request.
 * 
 * @param string $request_uri
 * @return string
 */
function uri(string $request_uri): string
{
    return urldecode(parse_url($request_uri, PHP_URL_PATH));
}

/**
 * Is mod_rewrite appropriate for this request? E.g. is it a request for a file?
 * 
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
 * Returns a function that is able to return response callables for a given request.
 * Note that array_reduce is chosen over foreach to keep in line with the preference
 * for recursions over loops in functional programming.
 * 
 * @param array $routes
 * @return Closure
 */
function match_request_to_route(array $routes): Closure
{
    return function (string $request_method, string $url) use ($routes): callable {
        $routes_to_use = $routes[$request_method];

        $patterns = array_keys($routes_to_use);

        $match = array_reduce($patterns, function ($carry, string $route) use ($url) {
            if (empty($carry) || $carry['match'] === false) {
                $carry = [
                    'route' => $route,
                    'match' => url_matches_route($url, $route),
                ];
            }

            return $carry;
        });

        if ($match['match'] === true) {
            return $routes_to_use[$match['route']];
        }

        return not_found_route();
    };
}

/**
 * A generic 404 route for our project.
 *
 * @return Closure
 */
function not_found_route(): Closure
{
    return function (array $all): string {
        if (php_sapi_name() !== 'cli') {
            header($all['server']['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
        }

        return '404 Not Found';
    };
}

/**
 * Return the response for a given server request.
 * 
 * @param array $routes
 * @param array $exposed_all
 * @return string
 */
function route(array $routes, array $exposed_all): string
{
    $callable = match_request_to_route($routes)(
        $exposed_all['server']['REQUEST_METHOD'],
        $exposed_all['server']['REQUEST_URI']
    );

    return $callable($exposed_all);
}

/**
 * Send a response with a header.
 * @todo Split out the sending of headers from the sending of a response.
 * @todo Is this even needed? Refactor as necessary.
 * 
 * @param string $response
 * @param array $server
 * @return string
 */
function response(string $response, array $server): string
{
    return $response;
}

/**
 * List all the PHP files inside a given path.
 * 
 * @param string $path
 * @return array
 */
function list_php_files_in_path(string $path): array
{
    return glob(join_file_folder_and_name($path, '/*.php'));
}

/**
 * Load all the configuration files in a given folder.
 * 
 * @param string $path
 * @return array
 */
function fetch_config_files(string $path): array
{
    $config_files = list_php_files_in_path($path);

    return array_reduce($config_files, function ($carry, $config_file) {
        $value = require $config_file;

        $carry[strtr(basename($config_file), ['.php' => ''])] = $value;

        return $carry;
    }, []);
}

/**
 * Return a generic PDO object for the provided DSN and connection details.
 * Good for database engine-agnostic applications.
 * 
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
 * Return a PDO object for the provided SQLite file location.
 * 
 * @param string $path_to_db_file
 * @return PDO
 */
function sqlite(string $path_to_db_file): PDO
{
    return pdo("sqlite:{$path_to_db_file}");
}

/**
 * Return a PDO object for the provided MySQL connection details and credentials.
 * 
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
 * Generate an accurate path to a file.
 * 
 * @param string $folder
 * @param string $filename
 * @return string
 */
function join_file_folder_and_name(string $folder, string $filename): string
{
    return rtrim($folder, '/') . '/' . trim($filename, '/');
}

/**
 * Returns a function that generates view responses.
 * 
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
 * Generate a random string of $length characters.
 * 
 * @param integer $length
 * @return string
 */
function generate_random(int $length): string
{
    return bin2hex(openssl_random_pseudo_bytes($length));
}

/**
 * Activate a session with our cookie.
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
 * Save an encryption key to our environment.
 * 
 * @param string $key
 * @return void
 */
function bind_encryption_key(string $key): void
{
    save_env('ENCRYPTION_KEY', $key);
}

/**
 * Do we have a CSRF token active right now?
 *
 * @return boolean
 */
function csrf_exists(): bool
{
    return array_key_exists('xsrf-token', expose_cookie());
}

/**
 * Fetch the currently active CSRF token.
 * 
 * @return string|null
 */
function csrf_get(): ?string
{
    return expose_cookie()['xsrf-token'] ?? null;
}

/**
 * Generate a CSRF token.
 * 
 * @param string $key
 * @return string
 */
function csrf_create(string $key): string
{
    return encrypt(generate_random(16), $key);
}

/**
 * Send our CSRF token via the active cookie.
 * 
 * @param string $csrf
 * @param integer $seconds
 * @return void
 */
function csrf_send(string $csrf, int $seconds = 3600): void
{
    setcookie('xsrf-token', $csrf, time() + $seconds);
}

/**
 * Have we loaded the project's encryption key?
 * 
 * @return boolean
 */
function has_encryption_key(): bool
{
    $env = expose_env();

    return empty($env['ENCRYPTION_KEY']) === false;
}

/**
 * Encrypt a given string with an encryption key.
 * Returns a base64-encoded encrypted string.
 * 
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
 * Fetch our project's generated encryption key.
 * 
 * @param string $path
 * @return string
 */
function get_encryption_key(string $path): string
{
    return base64_decode(file_get_contents(join_file_folder_and_name($path, '/.key')));
}

/**
 * Decrypts a string encrypted by our encrypt function.
 * 
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
 * Returns a copy of $_COOKIE.
 * 
 * @return array
 */
function expose_cookie(): array
{
    return $_COOKIE ?? [];
}

/**
 * Returns a copy of $_SESSION.
 * 
 * @return array
 */
function expose_session(): array
{
    return $_SESSION ?? [];
}
