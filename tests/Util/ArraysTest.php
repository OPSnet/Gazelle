<?php

namespace Gazelle\Util;

class ArraysTest extends \PHPUnit\Framework\TestCase {
    public function trimDataProvider() {
        return [
            [['test   ', '   test', '  test   '], ['test', 'test', 'test']],
            [[0, null, true, false, ' foo'], [0, null, true, false, 'foo']]
        ];
    }

    /**
     * @dataProvider trimDataProvider
     */
    public function testTrim($input, $expected) {
        $this->assertSame($expected, Arrays::trim($input));
    }
}
