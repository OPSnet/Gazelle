<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase {
	/**
	 * @dataProvider isIntegerDataProvider
	 */
	public function testIsInteger($value, $expected) {
		$this->assertEquals($expected, Type::isInteger($value));
	}

	public function isIntegerDataProvider() {
		return [
			[3, true],
			[3.5, false],
			["3", true],
			["3.5", false]
		];
	}

	/**
	 * @param $value
	 * @param $expected
	 *
	 * @dataProvider isBooleanValueDataProvider
	 */
	public function testIsBooleanValue($value, $expected) {
		$this->assertEquals($expected, Type::isBoolValue($value));
	}

	public function isBooleanValueDataProvider() {
		return [
			[true, true],
			[false, false],
			['true', true],
			['TrUe', true],
			['t', true],
			['T', true],
			['yes', true],
			['YeS', true],
			['on', true],
			['On', true],
			['1', true],
			['false', false],
			['FaLsE', false],
			['no', false],
			['No', false],
			['off', false],
			['OfF', false],
			['0', false],
			[1, true],
			[0, true],
			['aaa', null],
			[2, null],
			[1.0, null]
		];
	}
}