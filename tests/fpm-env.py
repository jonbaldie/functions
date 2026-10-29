import json
import os
from pathlib import Path
import socket
import struct
import subprocess
import sys
import tempfile
import time


ROOT = Path(__file__).resolve().parent.parent


def record(kind, content):
    padding = (-len(content)) % 8
    return struct.pack('!BBHHBB', 1, kind, 1, len(content), padding, 0) + content + bytes(padding)


def length(value):
    return bytes([value]) if value < 128 else struct.pack('!I', value | 0x80000000)


def request(port, script):
    params = {
        'SCRIPT_FILENAME': str(script),
        'REQUEST_METHOD': 'GET',
        'REQUEST_URI': '/',
        'SERVER_PROTOCOL': 'HTTP/1.1',
        'HTTP_X_PROBE': 'untrusted',
        'FUNCTIONS21_MARKER': 'request-injected',
    }
    encoded = b''
    for name, value in params.items():
        name, value = name.encode(), value.encode()
        encoded += length(len(name)) + length(len(value)) + name + value
    payload = (record(1, struct.pack('!HB5x', 1, 0)) + record(4, encoded)
               + record(4, b'') + record(5, b''))
    output = bytearray()
    with socket.create_connection(('127.0.0.1', port), timeout=5) as conn:
        conn.settimeout(5)
        conn.sendall(payload)
        def read_exact(size):
            data = bytearray()
            while len(data) < size:
                chunk = conn.recv(size - len(data))
                if not chunk:
                    raise RuntimeError('FPM closed the connection early')
                data.extend(chunk)
            return bytes(data)
        while True:
            version, kind, request_id, size, padding, _ = struct.unpack('!BBHHBB', read_exact(8))
            content = read_exact(size)
            read_exact(padding)
            if version != 1 or request_id != 1:
                raise RuntimeError('Invalid FastCGI response')
            if kind == 6:
                output.extend(content)
            elif kind == 3:
                if len(content) != 8 or content[:5] != bytes(5):
                    raise RuntimeError('FastCGI request failed')
                break
    _, separator, body = bytes(output).partition(b'\r\n\r\n')
    if not separator:
        raise RuntimeError('Missing HTTP response headers')
    return json.loads(body)


def probe(binary, directory, order):
    with socket.socket() as reservation:
        reservation.bind(('127.0.0.1', 0))
        port = reservation.getsockname()[1]
    script = directory / 'request.php'
    config = directory / 'fpm.conf'
    ini = directory / 'php.ini'
    ini.write_text('variables_order = ' + order + '\n')
    config.write_text(f'''[global]
pid = {directory}/fpm.pid
error_log = {directory}/fpm.log
daemonize = no
[probe]
listen = 127.0.0.1:{port}
pm = static
pm.max_children = 1
clear_env = no
''')
    env = {'PATH': os.defpath, 'FUNCTIONS21_MARKER': 'synthetic',
           'FUNCTIONS21_SAVED': 'synthetic',
           'TMPDIR': str(directory)}
    process = subprocess.Popen([binary, '-F', '-y', str(config), '-c', str(ini)],
                               env=env, stdin=subprocess.DEVNULL,
                               stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        deadline = time.monotonic() + 10
        while True:
            if process.poll() is not None:
                raise RuntimeError(f'{order}: FPM exited before accepting requests')
            try:
                result = request(port, script)
                break
            except ConnectionRefusedError:
                if time.monotonic() >= deadline:
                    raise RuntimeError(f'{order}: FPM did not start in time')
                time.sleep(0.05)
        return result
    finally:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()


def main():
    binary = os.environ.get('PHP_FPM_BIN')
    if not binary:
        raise RuntimeError('Set PHP_FPM_BIN to a PHP-FPM binary')
    binary = str(Path(binary).resolve())
    with tempfile.TemporaryDirectory(prefix='functions21-fpm-') as temp:
        directory = Path(temp)
        source = json.dumps(str(ROOT / 'src' / 'functions.php'))
        (directory / 'request.php').write_text('''<?php
require %s;
putenv('FUNCTIONS21_ADDED=runtime');
\\Functions\\save_env('FUNCTIONS21_SAVED', false);
$env = \\Functions\\expose_env();
echo json_encode([
    'header_absent' => !array_key_exists('HTTP_X_PROBE', $env),
    'header_visible' => ($_ENV['HTTP_X_PROBE'] ?? null) === 'untrusted'
        && ($env['HTTP_X_PROBE'] ?? null) === 'untrusted',
    'marker_local' => ($env['FUNCTIONS21_MARKER'] ?? null) === 'synthetic',
    'runtime_visible' => ($env['FUNCTIONS21_ADDED'] ?? null) === 'runtime',
    'saved_false' => array_key_exists('FUNCTIONS21_SAVED', $env)
        && $env['FUNCTIONS21_SAVED'] === false,
]);
''' % source)
        results = {order: probe(binary, directory, order) for order in ('GPCS', 'EGPCS')}
    checks = [('GPCS', 'header_absent'), ('GPCS', 'marker_local'),
              ('GPCS', 'runtime_visible'), ('GPCS', 'saved_false'),
              ('EGPCS', 'header_visible')]
    failed = False
    for order, check in checks:
        passed = results[order].get(check) is True
        print(f'{order} {check}: {"PASS" if passed else "FAIL"}')
        failed |= not passed
    return int(failed)


if __name__ == '__main__':
    try:
        sys.exit(main())
    except PermissionError:
        print('FPM probe error: permission denied creating a socket or starting FPM', file=sys.stderr)
        sys.exit(1)
    except (OSError, RuntimeError, ValueError, KeyError) as error:
        print(f'FPM probe error: {type(error).__name__}', file=sys.stderr)
        sys.exit(1)
