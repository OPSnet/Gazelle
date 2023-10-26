<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class CryptoTest extends TestCase {
    public function testCryptoSanity(): void {
        $payload = "someteststring!";
        $cipher = \Gazelle\Util\Crypto::encrypt($payload, ENCKEY);
        $dec_cipher = base64_decode($cipher);
        \PHPUnit\Framework\assertFalse(str_contains($dec_cipher, $payload), 'crypto-dataleak');
        $decrypted = \Gazelle\Util\Crypto::decrypt($cipher, ENCKEY);
        \PHPUnit\Framework\assertEquals($payload, $decrypted, 'crypto-encrypt-decrypt');
    }

    public function testCryptoNondeterministic(): void {
        $payload = "someteststring!";
        $cipher1 = \Gazelle\Util\Crypto::encrypt($payload, ENCKEY);
        $cipher2 = \Gazelle\Util\Crypto::encrypt($payload, ENCKEY);
        \PHPUnit\Framework\assertNotEquals($cipher1, $cipher2, 'crypto-nondeterministic');
        $decrypted1 = \Gazelle\Util\Crypto::decrypt($cipher1, ENCKEY);
        $decrypted2 = \Gazelle\Util\Crypto::decrypt($cipher2, ENCKEY);
        \PHPUnit\Framework\assertEquals($decrypted1, $decrypted2, 'crypto-nondeterministic-decrypt');
    }
}
