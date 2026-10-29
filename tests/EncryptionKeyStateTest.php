<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class EncryptionKeyStateTest extends TestCase
{
    public function ambientKeys(): array
    {
        return [
            'GPCS base64' => ['GPCS', true],
            'GPCS raw' => ['GPCS', false],
            'EGPCS base64' => ['EGPCS', true],
            'EGPCS raw' => ['EGPCS', false],
        ];
    }

    /** @dataProvider ambientKeys */
    public function testAmbientKeyDoesNotPreventConditionalBinding(string $order, bool $encoded): void
    {
        $ambient = $encoded ? base64_encode(str_repeat('a', 32)) : str_repeat('a', 32);
        $result = $this->runRequest($order, $ambient, <<<'PHP'
$ambient = getenv('ENCRYPTION_KEY');
$bound = str_repeat('b', 32);
$visible = \Functions\env('ENCRYPTION_KEY') === $ambient;
$before = \Functions\has_encryption_key();
\Functions\has_encryption_key() || \Functions\bind_encryption_key($bound);
echo json_encode([$visible, $before, \Functions\env('ENCRYPTION_KEY') === $bound, \Functions\has_encryption_key()]);
PHP);

        $this->assertSame([true, false, true, true], $result);
    }

    public function savedValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'false' => [false],
            'zero' => [0],
            'string zero' => ['0'],
        ];
    }

    /** @dataProvider savedValues */
    public function testFalseySavedValueClearsBoundState($value): void
    {
        $script = <<<'PHP'
$ambient = \Functions\has_encryption_key();
\Functions\save_env('ENCRYPTION_KEY', 'synthetic');
$saved = \Functions\has_encryption_key();
\Functions\save_env('ENCRYPTION_KEY', VALUE);
echo json_encode([$ambient, $saved, \Functions\has_encryption_key(), \Functions\env('ENCRYPTION_KEY') === VALUE]);
PHP;
        $script = str_replace('VALUE', var_export($value, true), $script);

        $this->assertSame([false, true, false, true], $this->runRequest('EGPCS', str_repeat('a', 32), $script));
    }

    public function mutations(): array
    {
        return [
            'overwrite' => ["\$_ENV['ENCRYPTION_KEY'] = 'replacement';"],
            'unset' => ["unset(\$_ENV['ENCRYPTION_KEY']);"],
        ];
    }

    /** @dataProvider mutations */
    public function testDirectMutationRevokesSavedKey(string $mutation): void
    {
        $script = <<<'PHP'
\Functions\save_env('ENCRYPTION_KEY', 'synthetic');
$saved = \Functions\has_encryption_key();
MUTATION
echo json_encode([$saved, \Functions\has_encryption_key()]);
PHP;

        $this->assertSame([true, false], $this->runRequest('EGPCS', str_repeat('a', 32), str_replace('MUTATION', $mutation, $script)));
    }

    private function runRequest(string $order, string $ambient, string $script): ?array
    {
        $program = 'require ' . var_export(__DIR__ . '/../src/functions.php', true) . ';' . $script;
        $process = proc_open([PHP_BINARY, '-d', 'variables_order=' . $order, '-r', $program], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, ['ENCRYPTION_KEY' => $ambient]);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), 'PHP subprocess failed.');

        $result = json_decode($output, true);
        return is_array($result) ? $result : null;
    }
}
