<?php

namespace Gazelle;

use \Gazelle\Util\Irc;
use \Gazelle\Util\IrcText;
use \PHPUnit\Framework\TestCase;

class IrcTextTest extends TestCase {
    public function testIrcText() {
        $this->assertEquals('abc', Irc::render('abc'), 'irc-plain');
        $this->assertEquals('%02def%02', urlencode(Irc::render(IrcText::Bold, 'def', IrcText::Bold)), 'irc-bold');
        $this->assertEquals('%1Dghi%1D', urlencode(Irc::render(IrcText::Italic, 'ghi', IrcText::Italic)), 'irc-italic');
        $this->assertEquals('a%0345b%0378c', urlencode(Irc::render('a', IrcText::Jade, 'b', IrcText::Canary, 'c')), 'irc-color');
    }
}
