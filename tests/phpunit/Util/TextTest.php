<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TextTest extends TestCase {
    public function testB64(): void {
        $this->assertEquals('d29yZA',  Text::base64UrlEncode('word'), 'text-b64-enc-1');
        $this->assertEquals('d29yZHM', Text::base64UrlEncode('words'), 'text-b64-enc-2');
        $this->assertEquals('wordy', Text::base64UrlDecode('d29yZHm'), 'text-b64-dec');
        $this->assertEquals(
            'match',
            Text::base64UrlDecode(Text::base64UrlEncode('match')),
            'text-b64-round-trip'
        );
    }

    public static function dataParse(): array {
        return [
            ['text-parse-code',      '<code>abc</code>',                   '[code]abc[/code]'],
            ['text-parse-important', '<important>def</important>',         '[important]def[/important]'],
            ['text-parse-pad',       '<pad pad="10px">abc</pad>',          '[pad=10px]abc[/pad]'],
            ['text-parse-pre',       '<pre>abc</pre>',                     '[pre]abc[/pre]'],
            ['text-parse-strong',    '<strong>abc</strong>',               '[b]abc[/b]'],
            [
                'text-parse-align',
                '<div style="text-align: center;">abc</div>',
                '[align=center]abc[/align]'
            ],
            [
                'text-parse-artist',
                '<a href="artist.php?artistname=abc">abc</a>',
                '[artist]abc[/artist]'],
            [
                'text-parse-color',
                '<span style="color: #feabac">abc</span>',
                '[color=#feabac]abc[/color]'
            ],
            [
                'text-parse-img',
                '<img src="http://to.be/some.jpg">',
                '[img]http://to.be/some.jpg[/img]',
            ],
            [
                'text-parse-italic',
                '<span style="font-style: italic">def</span>',
                '[i]def[/i]'
            ],
            [
                'text-parse-ol',
                '<ol class="postlist"><li>abc</li><li>def</li></ol>',
                "[#]abc\n[#]def\n",
            ],
            [
                'text-parse-para',
                '<p>abc</p><p>def</p>',
                "abc\ndef\n",
            ],
            [
                'text-parse-quote',
                "<strong class=\"quoteheader\">abc</strong> wrote:<blockquote>many words</blockquote>",
                "[quote=abc]many words[/quote]",
            ],
            [
                'text-parse-size',
                '<span class="size7">abc</span>',
                '[size=7]abc[/size]',
            ],
            [
                'text-parse-spoiler',
                '<strong>abc</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a><blockquote class="hidden spoiler">def</blockquote>',
                '[spoiler=abc]def[/spoiler]',
            ],
            [
                'text-parse-ul',
                '<ul class="postlist"><li>abc</li><li>def</li></ul>',
                "[*]abc\n[*]def\n",
            ],
            [
                'text-parse-underline',
                '<span style="text-decoration: underline">abc</span>',
                '[u]abc[/u]'
            ],
            [
                'text-parse-url',
                '<a href="http://to.be/some.html">text</a>',
                '[url=http://to.be/some.html]text[/url]'
            ],
            [
                'text-parse-user',
                '<a href="user.php?action=search&search=abc">abc</a>',
                '[user]abc[/user]'
            ],
        ];
    }

    #[DataProvider('dataParse')]
    public function testParseHtml(string $name, string $html, string $expected): void {
        $this->assertEquals($expected, Text::parseHtml($html), $name);
    }
}
