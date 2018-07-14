<?php

namespace Gazelle\Util;

class Text {
	/**
	 * Determine if the $haystack starts with $needle
	 *
	 * @param string  $haystack String to search in
	 * @param string  $needle String to search for
	 * @param boolean $case_insensitive flag to ignore case of the $haystack and $needle
	 * @return boolean True if $Needle is a prefix of $Haystack
	 */
	public static function startsWith($haystack, $needle, $case_insensitive = false) {
		if($case_insensitive) {
			$haystack = strtolower($haystack);
			$needle = strtolower($needle);
		}
		return substr($haystack, 0, strlen($needle)) === $needle;
	}

	/**
	 * Determine if the $haystack ends with $needle
	 *
	 * @param string  $haystack String to search in
	 * @param string  $needle String to search for
	 * @param boolean $case_insensitive flag to ignore case of the $haystack and $needle
	 * @return boolean True if $Needle is a suffix of $Haystack
	 */
	public static function endsWith($haystack, $needle, $case_insensitive = false) {
		if($case_insensitive) {
			$haystack = strtolower($haystack);
			$needle = strtolower($needle);
		}
		return ($needle !== null && strlen($needle) === 0) || substr($haystack, -strlen($needle)) === $needle;
	}

	public static function base64UrlEncode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	public static function base64UrlDecode($data) {
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=',
			STR_PAD_RIGHT));
	}
}
