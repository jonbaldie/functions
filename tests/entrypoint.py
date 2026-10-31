import json
import pathlib
import socket
import subprocess
import time
import unittest
import urllib.request
from html.parser import HTMLParser


ROOT = pathlib.Path(__file__).resolve().parent.parent


class Scripts(HTMLParser):
    def __init__(self):
        super().__init__()
        self.scripts = []

    def handle_starttag(self, tag, attrs):
        if tag == 'script':
            self.scripts.append(dict(attrs))


class EntrypointTest(unittest.TestCase):
    def test_welcome_page_loads_the_complete_entrypoint(self):
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            port = listener.getsockname()[1]
        server = subprocess.Popen(
            ['php', '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/index.php'],
            cwd=ROOT, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        try:
            url = f'http://127.0.0.1:{port}'
            for attempt in range(100):
                try:
                    with urllib.request.urlopen(url, timeout=2) as response:
                        html = response.read().decode()
                    break
                except urllib.error.URLError:
                    if attempt == 99 or server.poll() is not None:
                        raise
                    time.sleep(0.05)
            page = Scripts()
            page.feed(html)
            expected = json.loads((ROOT / 'public/build/entrypoints.json').read_text())['entrypoints']['app']['js']
            scripts = [script for script in page.scripts if script.get('src', '').startswith('/build/')]
            self.assertEqual(expected, [script['src'] for script in scripts])
            if len(scripts) > 1:
                self.assertTrue(all('async' not in script for script in scripts))
            for script in scripts:
                with urllib.request.urlopen(url + script['src'], timeout=2) as response:
                    self.assertEqual(200, response.status)
        finally:
            server.terminate()
            server.wait(timeout=5)


if __name__ == '__main__':
    unittest.main()
