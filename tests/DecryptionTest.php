<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use function Functions\decrypt;
use function Functions\encrypt;

class DecryptionTest extends TestCase
{
    public function testRoundTripsEmptyAsciiAndBinaryPlaintext()
    {
        $key = str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        foreach (['', 'foo', str_repeat("\x00\xff\x80foo", 1024)] as $plaintext) {
            $this->assertSame($plaintext, decrypt(encrypt($plaintext, $key), $key));
        }
    }

    public function testRejectsEverySingleByteChange()
    {
        $key = str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encoded = encrypt('foo', $key);
        $decoded = base64_decode($encoded, true);

        $this->assertSame('foo', decrypt($encoded, $key));

        for ($index = 0; $index < strlen($decoded); $index++) {
            $tampered = $decoded;
            $tampered[$index] = chr(ord($tampered[$index]) ^ 1);

            $this->assertNull(decrypt(base64_encode($tampered), $key), 'Changed byte ' . $index);
        }
    }

    public function testRejectsADifferentValidKey()
    {
        $key = str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encoded = encrypt('foo', $key);

        $this->assertSame('foo', decrypt($encoded, $key));
        $this->assertNull(decrypt($encoded, str_repeat('x', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }

    public function testRejectsMalformedCiphertext()
    {
        $key = str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encoded = encrypt('foo', $key);
        $decoded = base64_decode($encoded, true);

        $this->assertSame('foo', decrypt($encoded, $key));

        foreach ([
            '',
            'not base64!!',
            base64_encode('short'),
            base64_encode(substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)),
            base64_encode(substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES - 1)),
            '!' . $encoded,
        ] as $malformed) {
            $this->assertNull(decrypt($malformed, $key));
        }
    }

    public function testWrongLengthKeyRemainsAProgrammingError()
    {
        $key = str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encoded = encrypt('foo', $key);

        $this->assertSame('foo', decrypt($encoded, $key));
        $this->expectException(SodiumException::class);

        decrypt($encoded, 'short');
    }
}
