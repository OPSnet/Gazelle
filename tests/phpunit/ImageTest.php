<?php

declare(strict_types=1);

namespace Gazelle;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase {
    public function testImageBasic(): void {
        $image = Image::fromUntrusted('https://some.image.host.example.com/image.jpg');
        $this->assertNull(
            $image->errorMessage(),
            'image-no-error'
        );
    }

    public static function providerImageRewrite(): array {
        return [
            [
                'amz-ecx',
                'http://ecx.images-amazon.com/images/123._456_.jpg',
                'http://ecx.images-amazon.com/images/123.jpg',
            ],
            [
                'amz-ssl',
                'https://images-na.ssl-images-amazon.com/images/I/123._456_.jpg',
                'https://images-na.ssl-images-amazon.com/images/I/123.jpg',
            ],
            [
                'amz-q',
                'https://images-na.ssl-images-amazon.com/images/I/123._456_.jpg?secret=1',
                'https://images-na.ssl-images-amazon.com/images/I/123.jpg',
            ],
            [
                'ddg-ec',
                'https://external-content.duckduckgo.com/iu/?u=https%3A%2F%2Fexample.com%2Fi.jpg',
                'https://example.com/i.jpg',
            ],
            [
                'ddg-proxy',
                'https://proxy.duckduckgo.com/iu/?u=https%3A%2F%2Fexample.com%2Fi.jpg',
                'https://example.com/i.jpg',
            ],
            [
                'lucida',
                'https://lucida.to/api/load?url=%2Fapi%2Fproxy%3Furl%3Dhttps%253A%252F%252Fexample.com%252Fi.jpg',
                'https://example.com/i.jpg',
            ],
            [
                'startpage',
                'https://www.startpage.com/av/proxy-image?piurl=https%3A%2F%2Fexample.com%2Fi.jpg&sp=48071700',
                'https://example.com/i.jpg',
            ],
            [
                'none',
                '',
                null,
            ],
            [
                'null',
                null,
                null,
            ],
            [
                'same',
                'https://www.example.com/andnowforsomethingcompletelydifferent.jpg',
                'https://www.example.com/andnowforsomethingcompletelydifferent.jpg',
            ],
        ];
    }

    #[DataProvider('providerImageRewrite')]
    public function testImageRewrite(string $label, string|null $url, string|null $expected): void {
        $this->assertSame(
            $expected,
            Image::fromUntrusted($url)->url(),
            "image-rewrite-{$label}",
        );
    }

    public function testImageBadHost(): void {
        $image = Image::fromUntrusted('https://phpunit.bad/naughty.jpeg');
        $this->assertSame(
            'Please rehost images from phpunit.bad elsewhere.',
            $image->errorMessage(),
            'image-bad-host',
        );
    }

    public function testImageInvalid(): void {
        $image = Image::fromUntrusted('<img src="https://some.image.host.example.com/image.jpg">');
        $this->assertSame(
            '“&lt;img src=&quot;https://some.image.host.example.com/image.jpg&quot;&gt;” does not look like a valid image url',
            $image->errorMessage(),
            'image-error-message',
        );
    }
}
