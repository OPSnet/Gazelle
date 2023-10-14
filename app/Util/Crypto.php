<?php

namespace Gazelle\Util;

class Crypto {
    protected const GCM_TAG_SIZE = 16;  // largest possible size - 128 bits
    protected const ENC_ALGO = 'AES-256-GCM';

    public static function encrypt(string $plaintext, string $key, string $aad = AUTHKEY): string {
        $iv_size = openssl_cipher_iv_length(static::ENC_ALGO);
        if ($iv_size === false) {
            throw new \Exception("internal openssl iv error");
        }
        $key = static::hash($key);
        $iv = openssl_random_pseudo_bytes($iv_size);
        $cipher = openssl_encrypt(
            $plaintext, static::ENC_ALGO, $key, OPENSSL_RAW_DATA, $iv,
            $tag, $aad, static::GCM_TAG_SIZE);
        if ($cipher === false) {
            throw new \Exception("internal openssl_encrypt error");
        }
        return base64_encode($iv.$tag.$cipher);
    }

    public static function decrypt(string $ciphertext, string $key, string $aad = AUTHKEY): string|false {
        if (empty($ciphertext)) {
            return false;
        }

        $data = base64_decode($ciphertext);
        if (!$data) {
            return false;
        }
        $iv_size = openssl_cipher_iv_length(static::ENC_ALGO);
        if ($iv_size === false) {
            throw new \Exception("internal openssl iv error");
        }
        $key = static::hash($key);
        $iv = substr($data, 0, $iv_size);
        $tag = substr($data, $iv_size, static::GCM_TAG_SIZE);
        return openssl_decrypt(substr($data, $iv_size + static::GCM_TAG_SIZE),
            static::ENC_ALGO, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    }

    public static function dbEncrypt(string $plaintext): string|false {
        return apcu_exists('DB_KEY') ? Crypto::encrypt($plaintext, apcu_fetch('DB_KEY')) : false;
    }

    public static function dbDecrypt(string $ciphertext): string|false {
        return apcu_exists('DB_KEY') ? Crypto::decrypt($ciphertext, apcu_fetch('DB_KEY')) : false;
    }

    protected static function hash(string $payload): string {
        return hash('sha3-256', $payload, true);
    }
}
