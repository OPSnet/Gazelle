<?php

use \Gazelle\Manager\Notification;

class Misc {
    /**
     * Send an email.
     *
     * @param string $To the email address to send it to.
     * @param string $Subject
     * @param string $Body
     * @param string $From The user part of the user@MAIL_HOST email address.
     * @param string $ContentType text/plain or text/html
     */

    public static function send_email($To, $Subject, $Body, $From, $ContentType = 'text/plain') {
        $Headers = 'MIME-Version: 1.0'."\r\n";
        $Headers .= 'Content-type: text/plain; charset=iso-8859-1'."\r\n";
        $Headers .= 'From: '.SITE_NAME.' <'.$From.'@'.MAIL_HOST.'>'."\r\n";
        $Headers .= 'Reply-To: '.$From.'@'.MAIL_HOST."\r\n";
        $Headers .= 'Message-Id: <'.randomString().'@'.MAIL_HOST.">\r\n";
        $Headers .= 'X-Priority: 3'."\r\n";
        mail($To, $Subject, $Body, $Headers, "-f $From@".MAIL_HOST);
    }

    /**
     * Variant of in_array with trailing wildcard support
     *
     * @param string $Needle, array $Haystack
     * @return boolean true if (substring of) $Needle exists in $Haystack
     */
    public static function in_array_partial($Needle, $Haystack) {
        static $Searches = [];
        if (array_key_exists($Needle, $Searches)) {
            return $Searches[$Needle];
        }
        foreach ($Haystack as $String) {
            if (substr($String, -1) == '*') {
                if (!strncmp($Needle, $String, strlen($String) - 1)) {
                    $Searches[$Needle] = true;
                    return true;
                }
            } elseif (!strcmp($Needle, $String)) {
                $Searches[$Needle] = true;
                return true;
            }
        }
        $Searches[$Needle] = false;
        return false;
    }

    /**
     * HTML escape an entire array for output.
     * @param array $Array, what we want to escape
     * @param boolean|array $Escape
     *    if true, all keys escaped
     *    if false, no escaping.
     *    If array, it's a list of array keys not to escape.
     * @param boolean $Reverse reverses $Escape such that then it's an array of keys to escape
     * @return array mutated version of $Array with values escaped.
     */
    public static function display_array($Array, $Escape = [], $Reverse = false) {
        foreach ($Array as $Key => $Val) {
            if ((!is_array($Escape) && $Escape == true) || (!$Reverse && !in_array($Key, $Escape)) || ($Reverse && in_array($Key, $Escape))) {
                $Array[$Key] = display_str($Val);
            }
        }
        return $Array;
    }

    /**
     * Searches for a key/value pair in an array.
     *
     * @return array of results
     */
    public static function search_array($Array, $Key, $Value) {
        $Results = [];
        if (is_array($Array))
        {
            if (isset($Array[$Key]) && $Array[$Key] == $Value) {
                $Results[] = $Array;
            }

            foreach ($Array as $subarray) {
                $Results = array_merge($Results, self::search_array($subarray, $Key, $Value));
            }
        }
        return $Results;
    }
}
