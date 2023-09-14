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
    protected array $option;
    protected array $postData;
    protected CurlMethod $method = CurlMethod::GET;

    public function __construct() {
        $this->curl = curl_init();
    }

    public function __destruct() {
        curl_close($this->curl);
    }

    public function setMethod(CurlMethod $method): static {
        $this->method = $method;
        return $this;
    }

    public function setOption(int $option, $value): static {
        $this->option[$option] = $value;
        return $this;
    }

    /**
     * Set the POST key/value parameters.
     *
     * Implicity switches the HTTP method to POST and sets the content-type
     * to multipart/form-data.
     */
    public function setPostData(array $postData): static {
        $this->method   = CurlMethod::POST;
        $this->postData = $postData;
        return $this;
    }

    public function setUseProxy(bool $useProxy): static {
        $this->useProxy = $useProxy;
        return $this;
    }

    public function fetch(string $url): bool {
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
        $proxy = httpProxy();
        if ($proxy && $this->useProxy) {
            curl_setopt_array($this->curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY           => $proxy,
            ]);
        }
        if (!empty($this->postData)) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->postData);
        }
        if (!empty($this->option)) {
            curl_setopt_array($this->curl, $this->option);
        }

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
