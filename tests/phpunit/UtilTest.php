<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UtilTest extends TestCase {
    public function setUp(): void {}

    public function tearDown(): void {}

    public function testUtil() {
        $this->assertEquals(2,    article(2),       'article-2-a');
        $this->assertEquals(3,    article(3, 'an'), 'article-3-an');
        $this->assertEquals('a',  article(1),       'article-1-a');
        $this->assertEquals('an', article(1, 'an'), 'article-1-an');

        $this->assertEquals('',        display_str([]),       'display-str-array');
        $this->assertEquals('',        display_str(null),     'display-str-null');
        $this->assertEquals('',        display_str(false),    'display-str-false');
        $this->assertEquals(42,        display_str(42),       'display-str-42');
        $this->assertEquals('a&lt;b',  display_str('a<b'),    'display-str-a-lt-b');
        $this->assertEquals('&#8364;', display_str('&#128;'), 'display-str-entity-128');
        $this->assertEquals('&#376;',  display_str('&#159;'), 'display-str-entity-159');

        $this->assertEquals('a<b', reverse_display_str('a<b'),     'display-revstr-a-lt-b');
        $this->assertEquals(' ',   reverse_display_str('&#8364;'), 'display-revstr-entity-128');
        $this->assertEquals('Ÿ',   reverse_display_str('&#376;'),  'display-revstr-entity-159');

        $this->assertTrue(is_number(1), 'is-number-1');
        $this->assertFalse(is_number(3.14), 'is-number-3.14');
        $this->assertFalse(is_number('abc'), 'is-number-abc');

        $this->assertEquals('s',  plural(2),       'plural-2-s');
        $this->assertEquals('es', plural(3, 'es'), 'plural-3-es');
        $this->assertEquals('',   plural(1),       'plural-1-s');
        $this->assertEquals('',   plural(1, 'es'), 'plural-1-es');

        $browser = parse_user_agent("Lidarr/1.2.4 (windows 95)");
        $this->assertEquals('Lidarr', $browser['Browser'], 'ua-lidarr-name');
        $this->assertEquals('1.2', $browser['BrowserVersion'], 'ua-lidarr-version');
        $this->assertEquals('Windows', $browser['OperatingSystem'], 'ua-lidarr-os-name');
        $this->assertEquals('95', $browser['OperatingSystemVersion'], 'ua-lidarr-os-version');

        $browser = parse_user_agent("VarroaMusica/1234dev");
        $this->assertEquals('VarroaMusica', $browser['Browser'], 'ua-varroa-name');
        $this->assertEquals('1234', $browser['BrowserVersion'], 'ua-varroa-version');
        $this->assertNull($browser['OperatingSystem'], 'ua-varroa-os-name');
        $this->assertNull($browser['OperatingSystemVersion'], 'ua-varroa-os-version');

        $this->assertEquals('?,?,?', placeholders(['a', 'b', 'c']), 'placeholders-3');
        $this->assertEquals('(?),(?)', placeholders(['d', 'e'], '(?)'), 'placeholders-custom');

        $this->assertEquals(32, strlen(randomString()), 'random-string');

        $this->assertEquals('--------.txt', safeFilename('"-*-/-:-<->-?-\\-|.txt'), 'safe-filename');

        $this->assertEquals('abc def ghi', shortenString('abc def ghi', 20), 'shorten-string-unchanged');
        $this->assertEquals('abc def…', shortenString('abc def ghi', 10), 'shorten-string-10-ellipsis');
        $this->assertEquals('abc def', shortenString('abc def ghi', 10, false, false), 'shorten-string-10-no-ellipsis');
        $this->assertEquals('abcdefghij…', shortenString('abcdefghijklm', 10, true), 'shorten-string-13-shorten-ellipsis');
        $this->assertEquals('abcdefghij', shortenString('abcdefghijklm', 10, true, false), 'shorten-string-13-shorten-no-ellipsis');
        $this->assertEquals('abcdefghij…', shortenString('abcdefghijklm', 10, false), 'shorten-string-13-shorten-ellipsis');
    }
}
