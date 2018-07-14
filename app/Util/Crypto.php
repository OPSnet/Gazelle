<?php

namespace Gazelle\Util;

class Crypto {
	public static function encrypt($plaintext, $key) {
		$iv_size = openssl_cipher_iv_length('AES-128-CBC');
		$iv = openssl_random_pseudo_bytes($iv_size);
		return base64_encode($iv.openssl_encrypt($plaintext, 'AES-128-CBC', $key,
			OPENSSL_RAW_DATA, $iv));
	}

	public static function dbEncrypt($plaintext) {
		if (apcu_exists('DB_KEY')) {
			return Crypto::encrypt($plaintext, apcu_fetch('DB_KEY'));
		} else {
			return false;
		}
	}

	public static function decrypt($ciphertext, $key) {
		$iv_size = openssl_cipher_iv_length('AES-128-CBC');
		$iv = substr(base64_decode($ciphertext), 0, $iv_size);
		$ciphertext = substr(base64_decode($ciphertext), $iv_size);
		return openssl_decrypt($ciphertext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
	}

	public static function dbDecrypt($ciphertext) {
		if (apcu_exists('DB_KEY')) {
			return Crypto::decrypt($ciphertext, apcu_fetch('DB_KEY'));
		} else {
			return false;
		}
	}
}
