<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase {
	/**
	 * @dataProvider providerTimestamp
	 * @param $timestamp
	 * @param $expected
	 */
	function testTimeAgo($timestamp, $expected) {
		$this->assertEquals($expected, Time::timeAgo($timestamp), '', 1);
	}

	function providerTimestamp() {
		return [
			['0000-00-00 00:00:00', false],
			[null, false],
			['not a valid timestamp', false],
			['5', 5],
			[1413, 1413],
			['2000-01-01 01:01:01', time() - strtotime('2000-01-01 01:01:01')]
		];
	}

	/**
	 * @param $hours
	 * @param $expected
	 *
	 * @dataProvider providerHours
	 */
	function testConvertHours($hours, $expected) {
		$this->assertEquals($expected, Time::convertHours($hours, 2, false));
	}

	function providerHours() {
		return [
			[0, 'Never'],
			[1, '1h'],
			[24, '1d']
		];
	}

	function testTimeOffset() {
		$this->assertEquals(Time::timeOffset(-1), Time::timeMinus(1));
	}
}