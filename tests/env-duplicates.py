import ctypes
import json
import os
from pathlib import Path
import shutil


root = Path(__file__).resolve().parent.parent
program = r'''
require $argv[1];
set_error_handler(function () { return true; });
$before = [\Functions\expose_env()['FUNCTIONS_DUP'], \Functions\env('FUNCTIONS_DUP')];
$invalid = \Functions\env("FUNCTIONS_DUP\0suffix");
putenv('FUNCTIONS_DUP=changed');
echo json_encode([$before, $invalid, \Functions\expose_env()['FUNCTIONS_DUP'], \Functions\env('FUNCTIONS_DUP')]);
'''
php = os.fsencode(shutil.which('php'))
argv = [php, b'-d', b'variables_order=GPCS', b'-r', program.encode(), os.fsencode(root / 'src/functions.php')]
env = [b'FUNCTIONS_DUP=first', b'FUNCTIONS_DUP=second', b'PATH=/usr/bin:/bin']
read_fd, write_fd = os.pipe()
pid = os.fork()
if pid == 0:
    os.close(read_fd)
    os.dup2(write_fd, 1)
    os.close(write_fd)
    libc = ctypes.CDLL(None, use_errno=True)
    array = ctypes.c_char_p * (len(argv) + 1)
    environment = ctypes.c_char_p * (len(env) + 1)
    libc.execve(php, array(*argv, None), environment(*env, None))
    os._exit(127)
os.close(write_fd)
with os.fdopen(read_fd, 'rb') as stream:
    result = json.load(stream)
_, status = os.waitpid(pid, 0)
if not os.WIFEXITED(status) or os.WEXITSTATUS(status) != 0:
    raise RuntimeError('Duplicate environment process failed')
if result != [['first', 'first'], None, 'changed', 'changed']:
    raise RuntimeError(f'Unexpected duplicate environment result: {result!r}')
print('Duplicate environment semantics: PASS')
