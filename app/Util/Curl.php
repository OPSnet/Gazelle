<?php

namespace Gazelle\Util;

class Curl {

    protected $curl;
    protected $result;

    public function __construct() {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERAGENT      => FAKE_USERAGENT,
        ]);
        if (defined('HTTP_PROXY')) {
            curl_setopt_array($this->curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY => HTTP_PROXY,
            ]);
        }
    }

    public function __destruct() {
        curl_close($this->curl);
    }

    public function fetch(string $url): bool {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        $this->result = curl_exec($this->curl);
        return $this->result !== false || $this->responseCode() === 200;
    }

    public function result(): string {
        return $this->result;
    }

    public function responseCode(): int {
        return curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
    }
}
