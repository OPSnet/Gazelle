<?php

namespace Gazelle\Util;

enum CurlMethod {
    case GET;
    case HEAD;
    case POST;
    case PUT;
}

class Curl {

    protected $curl;
    protected $result;
    protected bool $useProxy = true;
    protected CurlMethod $method = CurlMethod::GET;

    public function __construct() {
        $this->curl = curl_init();
    }

    public function __destruct() {
        curl_close($this->curl);
    }

    public function setMethod(CurlMethod $method): Curl {
        $this->method = $method;
        return $this;
    }

    public function setUseProxy(bool $useProxy): Curl {
        $this->useProxy = $useProxy;
        return $this;
    }

    public function fetch(string $url): bool {
        if (HTTP_PROXY && $this->useProxy) {
            curl_setopt_array($this->curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY           => HTTP_PROXY,
            ]);
        }
        curl_setopt_array($this->curl, [
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERAGENT      => FAKE_USERAGENT,
            CURLOPT_URL            => $url,
            match ($this->method) {
                CurlMethod::GET  => CURLOPT_HTTPGET,
                CurlMethod::HEAD => CURLOPT_NOBODY,
                CurlMethod::POST => CURLOPT_POST,
                CurlMethod::PUT  => CURLOPT_PUT,
            } => true,
        ]);
        $this->result = curl_exec($this->curl);
        return $this->result !== false || $this->responseCode() === 200;
    }

    public function result(): ?string {
        return $this->result;
    }

    public function responseCode(): int {
        return curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
    }
}
