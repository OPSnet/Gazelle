<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;

class UtilTextTest extends TestCase {
    /**
     * @param $string
     * @param $substr
     * @param $case_insensitive
     * @param $expected
     *
     * @dataProvider providerStartsWith
     */
    public function testStartsWith($string, $substr, $case_insensitive, $expected) {
        $this->assertEquals($expected, Text::startsWith($string, $substr, $case_insensitive));
    }

    public function providerStartsWith() {
        return [
            ['arrow', '', false, true],
            ['arrow', 'arrow', false, true],
            ['arrow', 'a', false, true],
            ['arrow', 'f', false, false],
            [null, null, false, false],
            [null, 't', false, false],
            ['arrow', null, false, false],
            ['Arrow', 'arr', false, false],
            ['Arrow', 'a', true, true],
            ['Arrow', 'arrow', true, true],
            [null, null, true, true],
            [null, '', true, true]
        ];
    }

    /**
     * @param $string
     * @param $substr
     * @param $case_insensitive
     * @param $expected
     *
     * @dataProvider providerEndsWith
     */
    public function testEndsWith($string, $substr, $case_insensitive, $expected) {
        $this->assertEquals($expected, Text::endsWith($string, $substr, $case_insensitive));
    }

    public function providerEndsWith() {
        return [
            ['arrow', '', false, true],
            ['arrow', 'arrow', false, true],
            ['arrow', 'w', false, true],
            ['arrow', 'p', false, false],
            [null, null, false, false],
            [null, 't', false, false],
            ['arrow', null, false, false],
            ['Arrow', 'roW', false, false],
            ['ArroW', 'w', true, true],
            ['ArroW', 'arrow', true, true],
            [null, null, true, true],
            [null, '', true, true]
        ];
    }
}
