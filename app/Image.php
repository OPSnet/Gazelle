<?php

declare(strict_types=1);

namespace Gazelle;

class Image {
    protected string|null $errorMessage = null;

    public function __construct(
        protected string|null $url,
    ) {}

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
        $banned = Util\ImageProxy::badHost($url);
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

    public function url(): string|null {
        return $this->url;
    }
}
