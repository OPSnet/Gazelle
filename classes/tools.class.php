<?php
class Tools {

    /**
     * Geolocate an IP address using the database
     *
     * @param string|int $IP the ip to fetch the country for
     * @return string the country of origin
     */
    public static function geoip($IP) {
        static $IPs = [];
        if (isset($IPs[$IP])) {
            return $IPs[$IP];
        }
        if (is_number($IP)) {
            $Long = $IP;
        } else {
            $Long = sprintf('%u', ip2long($IP));
        }
        if (!$Long || $Long == 2130706433) { // No need to check cc for 127.0.0.1
            return 'localhost';
        }
        return '?';
    }

    /**
     * Gets the hostname for an IP address
     *
     * @param string $IP the IP to get the hostname for
     * @return string string hostname fetched
     */
    public static function get_host_by_ip($IP) {
        $testar = explode('.', $IP);
        if (count($testar) != 4) {
            return $IP;
        }
        for ($i = 0; $i < 4; ++$i) {
            if (!is_numeric($testar[$i])) {
                return $IP;
            }
        }

        $host = `host -W 1 $IP`;
        return ($host ? end(explode(' ', $host)) : $IP);
    }

    /**
     * Gets an hostname using AJAX
     *
     * @param string $IP the IP to fetch
     * @return string a span with JavaScript code
     */
    public static function get_host_by_ajax($IP) {
        static $IPs = [];
        $Class = strtr($IP, '.', '-');
        $HTML = '<span class="host_'.$Class.'">Resolving host...';
        if (!isset($IPs[$IP])) {
            $HTML .= '<script type="text/javascript">' .
                    '$(document).ready(function() {' .
                        '$.get(\'tools.php?action=get_host&ip='.$IP.'\', function(host) {' .
                            '$(\'.host_'.$Class.'\').html(host);' .
                        '});' .
                    '});' .
                '</script>';
        }
        $HTML .= '</span>';
        $IPs[$IP] = 1;
        return $HTML;
    }


    /**
     * Looks up the full host of an IP address, by system call.
     * Used as the server-side counterpart to get_host_by_ajax.
     *
     * @param string $IP The IP address to look up.
     * @return string the host.
     */
    public static function lookup_ip($IP) {
        $Output = explode(' ',shell_exec('host -W 1 '.escapeshellarg($IP)));
        if (count($Output) == 1 && empty($Output[0])) {
            //No output at all implies the command failed
            return '';
        }

        if (count($Output) != 5) {
            return false;
        } else {
            return trim($Output[4]);
        }
    }

    /**
     * Format an IP address with links to IP history.
     *
     * @param string $IP
     * @return string The HTML
     */
    public static function display_ip($IP) {
        $Line = display_str($IP).' ('.Tools::get_country_code_by_ajax($IP).') ';
        $Line .= '<a href="user.php?action=search&amp;ip_history=on&amp;ip='.display_str($IP).'&amp;matchtype=strict" title="Search" class="brackets tooltip">S</a>';

        return $Line;
    }

    public static function get_country_code_by_ajax($IP) {
        static $IPs = [];
        $Class = strtr($IP, '.', '-');
        $HTML = '<span class="cc_'.$Class.'">Resolving CC...';
        if (!isset($IPs[$IP])) {
            $HTML .= '<script type="text/javascript">' .
                    '$(document).ready(function() {' .
                        '$.get(\'tools.php?action=get_cc&ip='.$IP.'\', function(cc) {' .
                            '$(\'.cc_'.$Class.'\').html(cc);' .
                        '});' .
                    '});' .
                '</script>';
        }
        $HTML .= '</span>';
        $IPs[$IP] = 1;
        return $HTML;
    }
}
