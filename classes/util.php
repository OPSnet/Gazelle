<?php
// This is a file of miscellaneous functions that are called so damn often
// that it'd just be annoying to stick them in namespaces.

use Gazelle\Util\{Type, Time, Irc};

/**
 * Return true if the given string is an integer. The original Gazelle developers
 * must have thought the only numbers out there were integers when naming this function.
 *
 * @param mixed $Str
 * @return bool
 */
if (PHP_INT_SIZE === 4) {
    function is_number($Str) {
        if ($Str === null || $Str === '') {
            return false;
        }
        if (is_int($Str)) {
            return true;
        }
        if ($Str[0] == '-' || $Str[0] == '+') { // Leading plus/minus signs are ok
            $Str[0] = 0;
        }
        return ltrim($Str, "0..9") === '';
    }
} else {
    function is_number($Str) {
        return Type::isInteger($Str);
    }
}

function is_date($Date) {
    return Time::isValidDate($Date);
}

/**
 * Check that some given variables (usually in _GET or _POST) are numbers
 *
 * @param array $Base array that's supposed to contain all keys to check
 * @param array $Keys list of keys to check
 * @param mixed $Error error code or string to pass to the error() function if a key isn't numeric
 */
function assert_numbers(&$Base, $Keys, $Error = 0) {
    // make sure both arguments are arrays
    if (!is_array($Base) || !is_array($Keys)) {
        return;
    }
    foreach ($Keys as $Key) {
        if (!isset($Base[$Key]) || !is_number($Base[$Key])) {
            error($Error);
        }
    }
}

/**
 * Return true, false or null, depending on the input value's "truthiness" or "non-truthiness"
 *
 * @param $Value the input value to check for truthiness
 * @return true if $Value is "truthy", false if it is "non-truthy" or null if $Value was not
 *         a bool-like value
 */
function is_bool_value($Value) {
    return Type::isBoolValue($Value);
}

/**
 * HTML-escape a string for output.
 * This is preferable to htmlspecialchars because it doesn't screw up upon a double escape.
 *
 * @param string $Str
 * @return string escaped string.
 */
function display_str($Str) {
    if ($Str === null || $Str === false || is_array($Str)) {
        return '';
    }
    if ($Str != '' && !is_number($Str)) {
        $Str = make_utf8($Str);
        $Str = mb_convert_encoding($Str, 'HTML-ENTITIES', 'UTF-8');
        $Str = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,6};)/m", '&amp;', $Str);

        $Replace = [
            "'",'"',"<",">",
            '&#128;','&#130;','&#131;','&#132;','&#133;','&#134;','&#135;','&#136;',
            '&#137;','&#138;','&#139;','&#140;','&#142;','&#145;','&#146;','&#147;',
            '&#148;','&#149;','&#150;','&#151;','&#152;','&#153;','&#154;','&#155;',
            '&#156;','&#158;','&#159;'
        ];

        $With = [
            '&#39;','&quot;','&lt;','&gt;',
            '&#8364;','&#8218;','&#402;','&#8222;','&#8230;','&#8224;','&#8225;','&#710;',
            '&#8240;','&#352;','&#8249;','&#338;','&#381;','&#8216;','&#8217;','&#8220;',
            '&#8221;','&#8226;','&#8211;','&#8212;','&#732;','&#8482;','&#353;','&#8250;',
            '&#339;','&#382;','&#376;'
        ];

        $Str = str_replace($Replace, $With, $Str);
    }
    return $Str;
}

/**
 * Un-HTML-escape a string for output.
 *
 * It's like the above function, but in reverse.
 *
 * @param string $Str
 * @return string unescaped string
 */
function reverse_display_str($Str) {
    if ($Str === null || $Str === false || is_array($Str)) {
        return '';
    }
    if ($Str != '' && !is_number($Str)) {
        $Replace = [
            '&#39;','&quot;','&lt;','&gt;',
            '&#8364;','&#8218;','&#402;','&#8222;','&#8230;','&#8224;','&#8225;','&#710;',
            '&#8240;','&#352;','&#8249;','&#338;','&#381;','&#8216;','&#8217;','&#8220;',
            '&#8221;','&#8226;','&#8211;','&#8212;','&#732;','&#8482;','&#353;','&#8250;',
            '&#339;','&#382;','&#376;'
        ];

        $With = [
            "'",'"',"<",">",
            '&#128;','&#130;','&#131;','&#132;','&#133;','&#134;','&#135;','&#136;',
            '&#137;','&#138;','&#139;','&#140;','&#142;','&#145;','&#146;','&#147;',
            '&#148;','&#149;','&#150;','&#151;','&#152;','&#153;','&#154;','&#155;',
            '&#156;','&#158;','&#159;'
        ];
        $Str = str_replace($Replace, $With, $Str);

        $Str = str_replace("&amp;", "&", $Str);
        $Str = mb_convert_encoding($Str, 'UTF-8', 'HTML-ENTITIES');
    }
    return $Str;
}

/**
 * Send a message to an IRC bot listening on SOCKET_LISTEN_PORT
 *
 * @param string $Raw An IRC protocol snippet to send.
 */
function send_irc($Raw) {
    Irc::sendRaw($Raw);
}


/**
 * Display a critical error and kills the page.
 *
 * @param string $Error Error type. Automatically supported:
 *    403, 404, 0 (invalid input), -1 (invalid request)
 *    If you use your own string for Error, it becomes the error description.
 * @param boolean $NoHTML If true, the header/footer won't be shown, just the description.
 * @param string $Log If true, the user is given a link to search $Log in the site log.
 */
function error($Error, $NoHTML = false, $Log = false) {
    global $Debug;
    require(SERVER_ROOT.'/sections/error/index.php');
    $Debug->profile();
    die();
}


/**
 * Convenience function for check_perms within Permissions class.
 *
 * @see Permissions::check_perms()
 *
 * @param string $PermissionName
 * @param int $MinClass
 * @return bool
 */
function check_perms($PermissionName, $MinClass = 0) {
    return Permissions::check_perms($PermissionName, $MinClass);
}

/**
 * Print JSON status result with an optional message and die.
 * DO NOT USE THIS FUNCTION!
 */
function json_die($Status, $Message="bad parameters") {
    json_print($Status, $Message);
    die();
}

/**
 * Print JSON status result with an optional message.
 */
function json_print($Status, $Message) {
    if (check_perms('site_debug')) {
        global $Debug;
        $Message['queries'] = $Debug->get_queries();
        $Message['searches'] = $Debug->get_sphinxql_queries();
    }
    if ($Status == 'success' && $Message) {
        print json_encode(['status' => $Status, 'response' => $Message]);
    } elseif ($Message) {
        print json_encode(['status' => $Status, 'error' => $Message]);
    } else {
        print json_encode(['status' => $Status, 'response' => []]);
    }
}

/**
 * Print the site's URL including the appropriate URI scheme, including the trailing slash
 *
 * @param bool $SSL - whether the URL should be crafted for HTTPS or regular HTTP
 * @return url for site
 */
function site_url($SSL = true) {
    return $SSL ? 'https://' . SSL_SITE_URL . '/' : 'http://' . NONSSL_SITE_URL . '/';
}

/**
 * The text of the pop-up confirmation when burning an FL token.
 *
 * @param integer $seeders - number of seeders for the torrent
 * @return string Warns if there are no seeders on the torrent
 */
function FL_confirmation_msg($seeders, $size) {
    $TokensToUse = ceil($size / BYTES_PER_FREELEECH_TOKEN);
    /* Coder Beware: this text is emitted as part of a Javascript single quoted string.
     * Any apostrophes should be avoided or escaped appropriately (with \\').
     */
    return ($seeders == 0)
        ? 'Warning! This torrent is not seeded at the moment, are you sure you want to use '.$TokensToUse.' Freeleech token(s) here?'
        : 'Are you sure you want to use '.$TokensToUse.' Freeleech token(s) here?';
}

/**
 * Utility function that unserializes an array, and then if the unserialization fails,
 * it'll then return an empty array instead of a null or false which will break downstream
 * things that require an incoming array
 *
 * @param string $array
 * @return array
 */
function unserialize_array($array) {
    $array = empty($array) ? [] : unserialize($array);
    return (empty($array)) ? [] : $array;
}

/**
 * Utility function for determining if checkbox should be checked if some $value is set or not
 * @param $value
 * @return string
 */
function isset_array_checked($array, $value) {
    return (isset($array[$value])) ? "checked" : "";
}

/**
 * Helper function to return an string of N elements from an array.
 *
 * (e.g. [2, 4, 6] into a list of query placeholders (e.g. '?,?,?')
 * By default '?' is used, but a custom placeholder may be specified,
 * such as '(?)' or '(?, now(), 100)', for use in a bulk insert.
 *
 * @param array $list The list of elements
 * @param string $placeholder ('?' by default).
 * @return string The resulting placeholder string.
 */
function placeholders(array $list, $placeholder = '?') {
    return implode(',', array_fill(0, count($list), $placeholder));
}

/**
 * Magical function.
 *
 * @param string $Str function to detect encoding on.
 * @return true if the string is in UTF-8.
 */
function is_utf8($Str) {
    return preg_match('%^(?:
        [\x09\x0A\x0D\x20-\x7E]              // ASCII
        | [\xC2-\xDF][\x80-\xBF]             // non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]         // excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  // straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]         // excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}      // planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          // planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}      // plane 16
        )*$%xs', $Str
    );
}

/**
 * Detect the encoding of a string and transform it to UTF-8.
 *
 * @param string $Str
 * @return UTF-8 encoded version of $Str
 */
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

/*
 * Generate a random string drawn from alphanumeric characters
 * but omitting lowercase l, uppercase I and O (to avoid confusion).
 *
 * @param  int    $length
 * @return string random alphanumeric string
 */
function randomString($len = 32) {
    $alphabet = str_split('abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789');
    $max = count($alphabet);
    $mask = (int)pow(2, ceil(log($size, 2))) - 1;
    $out = '';
    while (strlen($out) < $len) {
        $n = ord(openssl_random_pseudo_bytes(1)) & $mask;
        if ($n < $max) {
            $out .= $alphabet[$n];
        }
    }
    return $out;
}
