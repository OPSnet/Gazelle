<?php

namespace Gazelle\Util;

class Crypto {
    public static function encrypt($plaintext, $key) {
        $iv_size = openssl_cipher_iv_length('AES-128-CBC');
        $iv = openssl_random_pseudo_bytes($iv_size);
        return base64_encode($iv.openssl_encrypt($plaintext, 'AES-128-CBC', $key,
            OPENSSL_RAW_DATA, $iv));
    }

    public static function decrypt(string $ciphertext, string $key): string {
        if (empty($ciphertext)) {
            return '';
        }

        $data = base64_decode($ciphertext);
        $iv_size = openssl_cipher_iv_length('AES-128-CBC');
        $iv = substr($data, 0, $iv_size);
        return openssl_decrypt(substr($data, $iv_size), 'AES-128-CBC', $key,
            OPENSSL_RAW_DATA, $iv);
    }

    public static function dbEncrypt($plaintext) {
        return apcu_exists('DB_KEY') ? Crypto::encrypt($plaintext, apcu_fetch('DB_KEY')) : false;
    }

    public static function dbDecrypt($ciphertext) {
        return apcu_exists('DB_KEY') ? Crypto::decrypt($ciphertext, apcu_fetch('DB_KEY')) : false;
    }
}
