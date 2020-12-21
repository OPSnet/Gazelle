<?php
// This is a file of miscellaneous functions that are called so damn often
// that it'd just be annoying to stick them in namespaces.

use Gazelle\Manager\IPv4;
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
 * Awful anglo-centric hack for handling plurals ;-)
 *
 * @param $n the number
 * @return '' if 1, otherwise 's'
 */
function plural(int $n) {
    return $n == 1 ? '' : 's';
}

/**
 * Awful anglo-centric hack for handling articles
 *
 * @param $n the number
 * @param $article string to use if you don't want the default 'a'
 * @return 'a' (or $article) if $n == 1, otherwise $n
 */
function article(int $n, $article = 'a') {
    return $n == 1 ? $article : $n;
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
 * Determine the redirect header to use based on the client HTTP_REFERER or fallback
 *
 * @param string fallback URL if HTTP_REFERER is empty
 * @return string redirect URL
 */
function redirectUrl(string $fallback): string {
    return empty($_SERVER['HTTP_REFERER']) ? $fallback : $_SERVER['HTTP_REFERER'];
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
    require_once(__DIR__ . '/../sections/error/index.php');
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
 */
function json_die($Status, $Message="bad parameters") {
    json_print($Status, $Message);
    die();
}

/**
 * Print JSON status result with an optional message.
 */
function json_print($Status, $Message) {
    if ($Status == 'success' && $Message) {
        $response = ['status' => $Status, 'response' => $Message];
    } elseif ($Message) {
        $response = ['status' => $Status, 'error' => $Message];
    } else {
        $response = ['status' => $Status, 'response' => []];
    }

    print(json_encode(add_json_info($response)));
}

function json_error($Code) {
    echo json_encode(add_json_info(['status' => 'failure', 'error' => $Code, 'response' => []]));
    die();
}

function json_or_error($JsonError, $Error = null, $NoHTML = false) {
    if (defined('AJAX')) {
        json_error($JsonError);
    } else {
        error($Error ?? $JsonError, $NoHTML);
    }
}

function add_json_info($Json) {
    if (!isset($Json['info'])) {
        $Json = array_merge($Json, [
            'info' => [
                'source' => SITE_NAME,
                'version' => 1,
            ],
        ]);
    }
    if (!isset($Json['debug']) && check_perms('site_debug')) {
        /** @var DEBUG $Debug */
        $Debug = new Gazelle\Debug;
        $Json = array_merge($Json, [
            'debug' => [
                'queries' => $Debug->get_queries(),
                'searches' => $Debug->get_sphinxql_queries()
            ],
        ]);
    }
    return $Json;
}

/**
 * Hydrate an array from a query string (everything that follow '?')
 * This reimplements parse_str() and side-steps the issue of max_input_vars limits.
 *
 * Example:
 * in: li[]=14&li[]=31&li[]=58&li[]=68&li[]=69&li[]=54&li[]=5, param=li[]
 * parsed: ['li[]' => ['14', '31, '58', '68', '69', '5']]
 * out: ['14', '31, '58', '68', '69', '5']
 *
 * @param string query string from url
 * @param string url param to extract
 * @return array hydrated equivalent
 */
function parseUrlArgs(string $urlArgs, string $param): array {
    $list = [];
    $pairs = explode('&', $urlArgs);
    foreach ($pairs as $p) {
        [$name, $value] = explode('=', $p, 2);
        if (!isset($list[$name])) {
            $list[$name] = $value;
        } else {
            if (!is_array($list[$name])) {
                $list[$name] = [$list[$name]];
            }
            $list[$name][] = $value;
        }
    }
    return array_key_exists($param, $list) ? $list[$param] : [];
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

/**
 * Generate a random string drawn from alphanumeric characters
 * but omitting lowercase l, uppercase I and O (to avoid confusion).
 *
 * @param  int    $len
 * @return string random alphanumeric string
 */
function randomString($len = 32) {
    $alphabet = str_split('abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789');
    $max = count($alphabet);
    $mask = (int)pow(2, ceil(log($len, 2))) - 1;
    $out = '';
    while (strlen($out) < $len) {
        $n = ord(openssl_random_pseudo_bytes(1)) & $mask;
        if ($n < $max) {
            $out .= $alphabet[$n];
        }
    }
    return $out;
}

// TODO: reconcile this with log_attempt in login/index.php
function log_token_attempt(DB_MYSQL $db, int $userId = 0): void {
    $watch = new Gazelle\LoginWatch;
    $ipStr = $_SERVER['REMOTE_ADDR'];

    [$attemptId, $attempts, $bans] = $db->row('
        SELECT ID, Attempts, Bans
        FROM login_attempts
        WHERE IP = ?
        ', $_SERVER['REMOTE_ADDR']
    );

    if (!$attemptId) {
        $watch->create($ipStr, null, $userId);
        return;
    }

    $attempts++;
    $watch->setWatch($attemptId);
    if ($attempts < 6) {
        $watch->increment($userId, $ipStr, null);
        return;
    }
    $watch->ban($attempts, null, $userId);
    if ($bans > 9) {
        (new IPv4())->createBan(0, $ipStr, $ipStr, 'Automated ban per failed token usage');
    }
}

/**
 * Shorten a string
 *
 * @param string $text string to cut
 * @param int    $maxLength cut at length
 * @param bool   $force force cut at length instead of at closest word
 * @param bool   $ellipsis Show dots at the end
 * @return string formatted string
 */
function shortenString(string $text, int $maxLength, bool $force = false, bool $ellipsis = true): string {
    if (mb_strlen($text, 'UTF-8') <= $maxLength) {
        return $text;
    }
    if ($force) {
        $short = mb_substr($text, 0, $maxLength, 'UTF-8');
    } else {
        $short = mb_substr($text, 0, $maxLength, 'UTF-8');
        $words = explode(' ', $short);
        if (count($words) > 1) {
            array_pop($words);
            $short = implode(' ', $words);
        }
    }
    if ($ellipsis) {
        $short .= "\xE2\x80\xA6"; // U+2026 HORIZONTAL ELLIPSIS
    }
    return $short;
}

function display_array($Array, $Escape = []) {
    foreach ($Array as $Key => $Val) {
        if ((!is_array($Escape) && $Escape == true) || !in_array($Key, $Escape)) {
            $Array[$Key] = display_str($Val);
        }
    }
    return $Array;
}

function proxyCheck(string $IP): bool {
    global $AllowedProxies;
    foreach ($AllowedProxies as $allowed) {
        //based on the wildcard principle it should never be shorter
        if (strlen($IP) < strlen($allowed)) {
            continue;
        }

        //since we're matching bit for bit iterating from the start
        for ($j = 0, $jl = strlen($IP); $j < $jl; ++$j) {
            //completed iteration and no inequality
            if ($j === $jl - 1 && $IP[$j] === $allowed[$j]) {
                return true;
            }

            //wildcard
            if ($allowed[$j] === '*') {
                return true;
            }

            //inequality found
            if ($IP[$j] !== $allowed[$j]) {
                break;
            }
        }
    }
    return false;
}

/*** Time and date functions ***/

function is_date($Date) {
    return Time::isValidDate($Date);
}

function time_ago($TimeStamp) {
    return Time::timeAgo($TimeStamp);
}

/*
 * Returns a <span> by default but can optionally return the raw time
 * difference in text (e.g. "16 hours and 28 minutes", "1 day, 18 hours").
 */
function time_diff($TimeStamp, $Levels = 2, $Span = true, $Lowercase = false, $StartTime = false) {
    return Time::timeDiff($TimeStamp, $Levels, $Span, $Lowercase, $StartTime);
}

/**
 * Given a number of hours, convert it to a human readable time of
 * years, months, days, etc.
 *
 * @param $Hours
 * @param int $Levels
 * @param bool $Span
 * @return string
 */
function convert_hours($Hours,$Levels=2,$Span=true) {
    return Time::convertHours($Hours, $Levels, $Span);
}

/* SQL utility functions */

function time_plus($Offset) {
    return Time::timePlus($Offset);
}

function time_minus($Offset, $Fuzzy = false) {
    return Time::timeMinus($Offset, $Fuzzy);
}

function sqltime($timestamp = false) {
    return Time::sqlTime($timestamp);
}

function validDate($DateString) {
    return Time::validDate($DateString);
}

function is_valid_date($Date) {
    return Time::isValidDate($Date);
}

function is_valid_time($Time) {
    return Time::isValidTime($Time);
}

function is_valid_datetime($DateTime, $Format = 'Y-m-d H:i') {
    return Time::isValidDateTime($DateTime, $Format);
}

/*** Paranoia functions ***/

// The following are used throughout the site:
// uploaded, ratio, downloaded: stats
// lastseen: approximate time the user last used the site
// uploads: the full list of the user's uploads
// uploads+: just how many torrents the user has uploaded
// snatched, seeding, leeching: the list of the user's snatched torrents, seeding torrents, and leeching torrents respectively
// snatched+, seeding+, leeching+: the length of those lists respectively
// uniquegroups, perfectflacs: the list of the user's uploads satisfying a particular criterion
// uniquegroups+, perfectflacs+: the length of those lists
// If "uploads+" is disallowed, so is "uploads". So if "uploads" is in the array, the user is a little paranoid, "uploads+", very paranoid.

// The following are almost only used in /sections/user/user.php:
// requiredratio
// requestsfilled_count: the number of requests the user has filled
//   requestsfilled_bounty: the bounty thus earned
//   requestsfilled_list: the actual list of requests the user has filled
// requestsvoted_...: similar
// artistsadded: the number of artists the user has added
// torrentcomments: the list of comments the user has added to torrents
//   +
// collages: the list of collages the user has created
//   +
// collagecontribs: the list of collages the user has contributed to
//   +
// invitedcount: the number of users this user has directly invited

/**
 * Return whether currently logged in user can see $Property on a user with $Paranoia, $UserClass and (optionally) $UserID
 * If $Property is an array of properties, returns whether currently logged in user can see *all* $Property ...
 *
 * @param $Property The property to check, or an array of properties.
 * @param $Paranoia The paranoia level to check against.
 * @param $UserClass The user class to check against (Staff can see through paranoia of lower classed staff)
 * @param $UserID Optional. The user ID of the person being viewed
 * @return mixed   1 representing the user has normal access
                   2 representing that the paranoia was overridden,
                   false representing access denied.
 */

function check_paranoia($Property, $Paranoia, $UserClass, $UserID = false) {
    global $Classes;
    if ($Property == false) {
        return false;
    }
    if (!is_array($Paranoia)) {
        $Paranoia = unserialize($Paranoia);
    }
    if (!is_array($Paranoia)) {
        $Paranoia = [];
    }
    if (is_array($Property)) {
        $all = true;
        foreach ($Property as $P) {
            $all = $all && check_paranoia($P, $Paranoia, $UserClass, $UserID);
        }
        return $all;
    } else {
        if (($UserID !== false) && (G::$LoggedUser['ID'] == $UserID)) {
            return PARANOIA_ALLOWED;
        }

        $May = !in_array($Property, $Paranoia) && !in_array($Property . '+', $Paranoia);
        if ($May)
            return PARANOIA_ALLOWED;

        if (check_perms('users_override_paranoia', $UserClass)) {
            return PARANOIA_OVERRIDDEN;
        }
        $Override=false;
        switch ($Property) {
            case 'downloaded':
            case 'ratio':
            case 'uploaded':
            case 'lastseen':
                if (check_perms('users_mod', $UserClass))
                    return PARANOIA_OVERRIDDEN;
                break;
            case 'snatched': case 'snatched+':
                if (check_perms('users_view_torrents_snatchlist', $UserClass))
                    return PARANOIA_OVERRIDDEN;
                break;
            case 'uploads': case 'uploads+':
            case 'seeding': case 'seeding+':
            case 'leeching': case 'leeching+':
                if (check_perms('users_view_seedleech', $UserClass))
                    return PARANOIA_OVERRIDDEN;
                break;
            case 'invitedcount':
                if (check_perms('users_view_invites', $UserClass))
                    return PARANOIA_OVERRIDDEN;
                break;
        }
        return false;
    }
}
