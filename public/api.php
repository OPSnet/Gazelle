<?php
/*-- API Start Class -------------------------------*/
/*--------------------------------------------------*/
/* Simplified version of script_start, used for the    */
/* site API calls                                    */
/*--------------------------------------------------*/
/****************************************************/

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$ScriptStartTime = microtime(true); //To track how long a page takes to create

//Lets prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');
require_once(__DIR__.'/../classes/time.class.php');
require_once(__DIR__.'/../classes/paranoia.class.php');
require_once(__DIR__.'/../classes/util.php');

$Cache = new CACHE($MemcachedServers);
$DB = new DB_MYSQL;
$Debug = new DEBUG;
$Twig = new Environment(
    new FilesystemLoader(__DIR__.'/../templates'),
    ['cache' => __DIR__.'/../cache/twig']
);
$Debug->handle_errors();

G::initialize();

function json_error($Code) {
    echo json_encode(['status' => 400, 'error' => $Code, 'response' => []]);
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

function display_array($Array, $Escape = []) {
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
require_once(__DIR__.'/../sections/api/index.php');
