<?php

namespace Gazelle\Util;

class Proxy {
    public function __construct(
        protected readonly string $key,
        protected readonly string $bouncer
    ) {}

    public function fetch($url, $params, $cookies, $post, $headers = []) {
        $data = Crypto::encrypt(
            (string)json_encode([
                'url'     => $url,
                'params'  => $params,
                'cookies' => $cookies,
                'post'    => $post,
                'action'  => 'fetch',
                'headers' => $headers
            ], JSON_UNESCAPED_SLASHES),
            $this->key
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->bouncer);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, self::urlEncode($data));
        $result = curl_exec($curl);

        return json_decode(Crypto::decrypt(self::urlDecode($result), $this->key), true);
    }

    public static function urlEncode($data) {
        return rtrim(strtr($data, '+/', '-_'), '=');
    }

    public static function urlDecode($data) {
        return str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
    }
}
