<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase {
    /**
     * @param $value
     * @param $expected
     *
     * @dataProvider isIntegerDataProvider
     */
    public function testIsInteger($value, $expected) {
        $this->assertEquals($expected, Type::isInteger($value));
    }

    public function isIntegerDataProvider() {
        return [
            [0, true],
            [3, true],
            [3.5, false],
            ['3', true],
            ['3.5', false],
            [-1, true],
            ['-1.5', false],
            ['a', false],
            [null, false]
        ];
    }
}
