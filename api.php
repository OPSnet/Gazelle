<?php
/*-- API Start Class -------------------------------*/
/*--------------------------------------------------*/
/* Simplified version of script_start, used for the	*/
/* site API calls									*/
/*--------------------------------------------------*/
/****************************************************/

$ScriptStartTime=microtime(true); //To track how long a page takes to create

//Lets prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
	unset($_GET['clearcache']);
}

require_once('classes/config.php'); //The config contains all site wide configuration information as well as memcached rules

require_once(SERVER_ROOT.'/classes/debug.class.php'); //Require the debug class
require_once(SERVER_ROOT.'/classes/mysql.class.php'); //Require the database wrapper
require_once(SERVER_ROOT.'/classes/cache.class.php'); //Require the caching class
require_once(SERVER_ROOT.'/classes/time.class.php'); //Require the time class
require_once(SERVER_ROOT.'/classes/paranoia.class.php'); //Require the paranoia check_paranoia function
require_once(SERVER_ROOT.'/classes/regex.php');
require_once(SERVER_ROOT.'/classes/util.php');

$Cache = NEW CACHE($MemcachedServers); //Load the caching class
$DB = new DB_MYSQL;
$Debug = new DEBUG;
$Debug->handle_errors();

require(SERVER_ROOT.'/classes/classloader.php');

G::initialize();

function json_error($Code) {
	echo json_encode(array('status' => 400, 'error' => $Code, 'response' => array()));
	die();
}

function make_secret($Length = 32) {
	$NumBytes = (int) round($Length / 2);
	$Secret = bin2hex(openssl_random_pseudo_bytes($NumBytes));
	return substr($Secret, 0, $Length);
}

function make_utf8($Str) {
	if ($Str != '') {
		if (is_utf8($Str)) {
			$Encoding = 'UTF-8';
		}
		if (empty($Encoding)) {
			$Encoding = mb_detect_encoding($Str, 'UTF-8, ISO-8859-1');
		}
		if (empty($Encoding)) {
			$Encoding = 'ISO-8859-1';
		}
		if ($Encoding == 'UTF-8') {
			return $Str;
		} else {
			return @mb_convert_encoding($Str, 'UTF-8', $Encoding);
		}
	}
}

function is_utf8($Str) {
	return preg_match('%^(?:
		[\x09\x0A\x0D\x20-\x7E]			 // ASCII
		| [\xC2-\xDF][\x80-\xBF]			// non-overlong 2-byte
		| \xE0[\xA0-\xBF][\x80-\xBF]		// excluding overlongs
		| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} // straight 3-byte
		| \xED[\x80-\x9F][\x80-\xBF]		// excluding surrogates
		| \xF0[\x90-\xBF][\x80-\xBF]{2}	 // planes 1-3
		| [\xF1-\xF3][\x80-\xBF]{3}		 // planes 4-15
		| \xF4[\x80-\x8F][\x80-\xBF]{2}	 // plane 16
		)*$%xs', $Str
	);
}

function display_array($Array, $Escape = array()) {
	foreach ($Array as $Key => $Val) {
		if ((!is_array($Escape) && $Escape == true) || !in_array($Key, $Escape)) {
			$Array[$Key] = display_str($Val);
		}
	}
	return $Array;
}

header('Expires: '.date('D, d M Y H:i:s', time() + (2 * 60 * 60)).' GMT');
header('Last-Modified: '.date('D, d M Y H:i:s').' GMT');
header('Content-type: application/json');
require_once(SERVER_ROOT.'/sections/api/index.php');
