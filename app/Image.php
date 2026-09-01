<?php

declare(strict_types=1);

namespace Gazelle;

class Image {
    protected string|null $errorMessage = null;
    protected string|null $hostname     = null;
    protected bool $isAuthenticated     = false;

    public function __construct(
        protected string|null $url,
    ) {
        if ($url === null) {
            return;
        }
        $info = parse_url($url);
        if (is_array($info) && isset($info['host'])) {
            $this->hostname = strtolower($info['host']);
            if (isset(IMAGE_HOST_AUTHENTICATED[$this->hostname])) {
                $hoster = IMAGE_HOST_AUTHENTICATED[$this->hostname];
                if (preg_match($hoster['path-regexp'], $info['path'])) {
                    $this->isAuthenticated = true;
                }
            }
        }
    }

    public static function fromUntrusted(string|null $url): Image {
        if (is_string($url) && trim($url) === '') {
            return new Image(null);
        }
        if ($url === null) {
            // there is nothing else to do
            return new Image(null);
        }
        if (preg_match('#^https://(?:external-content|proxy)\.duckduckgo\.com/iu/\?u=(.*)$#', $url, $match)) {
            // unwrap ddg rehosting
            $url = urldecode($match[1]);
        } elseif (preg_match('#^https://www\.startpage\.com/av/proxy-image\?piurl=([^&]+)#', $url, $match)) {
            // unwrap startpage rehosting
            $url = urldecode($match[1]);
        } elseif (preg_match('#^https://lucida\.to/api/load\?url=%2Fapi%2Fproxy%3Furl%3D(.*)#', $url, $match)) {
            // unwrap lucida rehosting
            $url = urldecode(urldecode($match[1]));
        } elseif (str_starts_with($url, 'https://redacted.sh/')) {
            $parse = parse_url($url);
            if ($parse === false) {
                return new Image(null);
            }
            // map leading /t/ to /i/
            if (strpos($parse['path'], '/t/') === 0) {
                $parse['path'] = substr_replace($parse['path'], '/i/', 0, 3);
            }
            // strip out query args
            $url = "https://redacted.sh{$parse['path']}";
        }
        // strip amazon resizing
        // http://ecx.images-amazon.com/images/I/123._456_.jpg
        // https://images-na.ssl-images-amazon.com/images/I/123._456_.jpg
        if (preg_match('/(https?:\/\/(?:ec[12x]\.|images-(?:eu|fe|na)\.ssl-)images-amazon\.com\/images\/.+)\._.*_(\.jpg)/i', $url, $match)) {
            $url = $match[1] . $match[2];
        }
        $image = new Image($url);
        if (!preg_match(IMAGE_REGEXP, $url)) {
            $image->setErrorMessage(html_escape("“{$url}” does not look like a valid image url"));
        }
        $banned = array_find(
            IMAGE_HOST_BANNED,
            fn ($host) => stripos($url, $host) !== false
        );
        if ($banned) {
            $image->setErrorMessage(html_escape("Please rehost images from $banned elsewhere."));
        }
        return $image;
    }

    public function setErrorMessage(string $message): void {
        $this->errorMessage = $message;
    }

    /**
     * Message to be displayed to the user during data entry
     */
    public function errorMessage(): string|null {
        return $this->errorMessage;
    }

    /**
     * This asset must be fetch via an API call
     */
    public function isAuthenticated(): bool {
        return $this->isAuthenticated;
    }

    /**
     * Note: In production this method is overridden.
     */
    public function payload(string $signature): string {
        return $signature;
    }

    /**
     * This secret is used to sign the remote url
     */
    public function secret(): string|null {
        return $this->isAuthenticated()
            ? IMAGE_HOST_AUTHENTICATED[$this->hostname]['secret']
            : null;
    }

    /**
     * Authenticated images are signed to prevent abuse of private API keys
     */
    public function signature(): string {
        return urlencode_safe(substr(
            hash_hmac(DIGEST_ALGO, $this->url, $this->secret(), binary: true),
            0, 12)
        );
    }

    /**
     * The url of the image. It will always be passed through as-is, even
     * if it is from a hostname that requires authentication to be fetched.
     * For that, it is "wrapped" to be reinjected as a local url.
     */
    public function url(): string|null {
        return $this->url;
    }

    public function urlWrapped(): string {
        return SITE_URL
            . "/view.php?type=url&s={$this->signature()}&u="
            . urlencode($this->url);
    }
}
