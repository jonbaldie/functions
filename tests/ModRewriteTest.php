<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function Functions\mod_rewrite;

class ModRewriteTest extends TestCase
{
    private string $directory;
    private string $public;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/functions-rewrite-' . bin2hex(random_bytes(8));
        $this->public = $this->directory . '/public';
        mkdir($this->directory);
        mkdir($this->public);
        mkdir($this->public . '/build');
        mkdir($this->directory . '/public-other');
        file_put_contents($this->public . '/index.php', 'controller');
        file_put_contents($this->public . '/build/app.js', 'asset');
        file_put_contents($this->directory . '/secret', 'secret');
        file_put_contents($this->directory . '/public-other/secret', 'secret');
        symlink('index.php', $this->public . '/controller-link');
        symlink('build/app.js', $this->public . '/asset-link');
        symlink('../secret', $this->public . '/outside-link');
        symlink($this->public, $this->directory . '/public-link');
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/public-link');
        unlink($this->public . '/outside-link');
        unlink($this->public . '/asset-link');
        unlink($this->public . '/controller-link');
        unlink($this->public . '/index.php');
        unlink($this->public . '/build/app.js');
        rmdir($this->public . '/build');
        rmdir($this->public);
        unlink($this->directory . '/public-other/secret');
        rmdir($this->directory . '/public-other');
        unlink($this->directory . '/secret');
        rmdir($this->directory);
    }

    public function testHandoff(): void
    {
        $paths = [
            'nested asset' => ['/build/app.js', 'cli-server', true],
            'internal symlink' => ['/asset-link', 'cli-server', true],
            'other SAPI' => ['/build/app.js', 'fpm-fcgi', false],
            'empty' => ['', 'cli-server', false],
            'root' => ['/', 'cli-server', false],
            'directory' => ['/build/', 'cli-server', false],
            'missing' => ['/missing', 'cli-server', false],
            'front controller' => ['/index.php', 'cli-server', false],
            'front controller symlink' => ['/controller-link', 'cli-server', false],
            'traversal' => ['/../secret', 'cli-server', false],
            'sibling prefix' => ['/../public-other/secret', 'cli-server', false],
            'outside symlink' => ['/outside-link', 'cli-server', false],
        ];

        foreach ($paths as $name => [$uri, $sapi, $expected]) {
            $this->assertSame($expected, mod_rewrite($uri, $this->directory . '/public-link', $sapi), $name);
        }
    }

    public function testDecodedNullByteIsNotHandedOff(): void
    {
        $this->assertFalse(mod_rewrite("/\0", $this->public, 'cli-server'));
    }
}
