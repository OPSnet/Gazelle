<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RegexpTest extends TestCase {
    public static function dataCSSTrue(): array {
        return [
            ["test-css-localhost", "http://localhost/abc.css"],
            ["test-css-domain",    "https://sub.example.com/path/def.css"],
            ["test-css-query",     "https://example.com/path/static/def.css?v123"],
        ];
    }

    #[DataProvider('dataCSSTrue')]
    public function testCSSTrue(string $name, string $url): void {
        $this->assertSame(1, preg_match(CSS_REGEXP, $url), "re-css-$name-bare");
    }

    public static function dataImageTrue(): array {
        return [
            ["test-image-basic", "https://abc.def.gui/jkl/mno-123-a_z.jpg"],
        ];
    }

    #[DataProvider('dataImageTrue')]
    public function testImageTrue(string $name, string $url): void {
        $this->assertSame(1, preg_match(IMAGE_REGEXP, $url), "re-image-$name-bare");
    }

    public static function dataUrlTrue(): array {
        return [
            ["url-localhost",       "http://localhost"],
            ["url-localhost-slash", "http://localhost/"],
            ["url-dottedquad",      "http://10.11.12.13/"],
            ["url-domain",          "https://example.com"],
            ["url-domain-slash",    "https://example.com/"],
            ["url-path",            "https://example.com/path"],
            ["url-path-ext",        "https://example.com/path.e"],
            ["url-path-ext-ext",    "https://example.com/path.ext/path.ext"],
            ["url-path-query",      "https://example.com/path?query"],
            ["url-path-query-arg2", "https://example.com/path?query=1&val=2"],
            ["url-path-query-arg3", "https://example.com/path?query=1;val=2;v2=4"],
            ["url-path-query-frag", "https://example.com/path?query#frag"],
            ["url-path-frag",       "https://example.com/path#frag"],
            ["url-path-frag-dot",   "https://example.com/path#fr.ag"],
            ["url-noquery-0frag",   "https://example.com/?"],
            ["url-noquery-nofrag",  "https://example.com/?#"],
            ["url-query-nofrag",    "https://example.com/?query"],
            ["url-query-frag",      "https://example.com/?query#frag"],
            ["url-frag0",           "https://example.com/#"],
            ["url-frag1",           "https://example.com/#frag"],
        ];
    }

    #[DataProvider('dataUrlTrue')]
    public function testUrlTrue(string $name, string $url): void {
        $this->assertSame(1, preg_match(URL_REGEXP, $url), "re-url-$name");
    }

    public static function dataImageFalse(): array {
        return [
            ["test-img-elem",  "http://localhost/bcd.jpeg<a>"],
            ["test-img-quote", 'http://localhost/abc.png">'],
            ["test-img-pc",    "http://localhost/bcd.jpeg%"],
        ];
    }

    #[DataProvider('dataImageFalse')]
    public function testImageFalse(string $name, string $url): void {
        $this->assertSame(0, preg_match(IMAGE_REGEXP, $url), "re-image-f-$name");
    }

    public static function dataUrlFalse(): array {
        return [
            ["url-elem",  "http://localhost/</a>"],
            ["url-pc",    "http://localhost/%"],
            ["url-quote", 'http://localhost/">'],
        ];
    }

    #[DataProvider('dataUrlFalse')]
    public function testUrlFalse(string $name, string $url): void {
        $this->assertSame(0, preg_match(URL_REGEXP, $url), "re-url-f-$name");
    }

    public function testMatchMultipleImages(): void {
        $this->assertSame(
            3,
            preg_match_all(
                IMAGETEXT_REGEXP,
                'https://a.com/a.jpg and https://b.com/b.gif then https://c.com/c.png?x=1',
                $match,
            ),
            're-image-multi-match',
        );
        $this->assertSame(
            [
                'https://a.com/a.jpg',
                'https://b.com/b.gif',
                'https://c.com/c.png?x=1',
            ],
            $match[1],
            're-image-multi-found',
        );
    }

    public function testMatchMultipleUrls(): void {
        $this->assertSame(
            3,
            preg_match_all(
                URL_REGEXP_MULTI,
                'they are https://a.com/abcdef and https://b.com/b?x=1 then https://c.com/c.png#id=1',
                $match,
            ),
            're-url-multi-match',
        );
        $this->assertSame(
            [
                'https://a.com/abcdef',
                'https://b.com/b?x=1',
                'https://c.com/c.png#id=1',
            ],
            $match[1],
            're-image-url-found',
        );
    }
}
