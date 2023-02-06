<?php

namespace phpunit;

use PHPUnit\Framework\TestCase;
use Text;

class TextTest extends TestCase {
    public function testTeX() {
        $text = new Text();

        $basic = '[tex]some formula[/tex]';
        $expected = '<katex>some formula</katex>';
        $this->assertEquals($expected, $text->full_format($basic), 'text-tex-basic');

        $outer = '[size=4][tex]some formula[/tex][/size]';
        $expected = '<span class="size4"><katex>some formula</katex></span>';
        $this->assertEquals($expected, $text->full_format($outer), 'text-tex-outer');

        $nested = '[tex]some [b]formula[/b][/tex]';
        $expected = '<katex>some [b]formula[/b]</katex>';
        $this->assertEquals($expected, $text->full_format($nested), 'text-tex-nested');
    }
}
