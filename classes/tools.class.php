<?php
class Tools {

    /**
     * Geolocate an IP address using the database
     *
     * @param $IP the ip to fetch the country for
     * @return the country of origin
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
            return false;
        }
        return '?';
    }

    /**
     * Gets the hostname for an IP address
     *
     * @param $IP the IP to get the hostname for
     * @return hostname fetched
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
     * @param $IP the IP to fetch
     * @return a span with JavaScript code
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
        //TODO: use the G::$Cache
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
     * @param string IP
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

    /**
     * Disable an array of users.
     *
     * @param array $UserIDs (You can also send it one ID as an int, because fuck types)
     * @param BanReason 0 - Unknown, 1 - Manual, 2 - Ratio, 3 - Inactive, 4 - Unused.
     */
    public static function disable_users($UserIDs, $AdminComment, $BanReason = 1) {
        $QueryID = G::$DB->get_query_id();
        if (!is_array($UserIDs)) {
            $UserIDs = [$UserIDs];
        }
        G::$DB->query("
            UPDATE users_info AS i
            INNER JOIN users_main AS um ON (um.ID = i.UserID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = i.UserID) SET
                um.Enabled = '2',
                um.can_leech = '0',
                i.AdminComment = CONCAT('".sqltime()." - ".($AdminComment ? $AdminComment : 'Disabled by system')."\n\n', i.AdminComment),
                i.BanDate = now(),
                i.BanReason = '$BanReason',
                i.RatioWatchDownload = ".($BanReason == 2 ? 'uls.Downloaded' : "'0'")."
            WHERE um.ID IN(".implode(',', $UserIDs).') ');
        G::$Cache->decrement('stats_user_count', G::$DB->affected_rows());
        foreach ($UserIDs as $UserID) {
            G::$Cache->delete_value("enabled_$UserID");
            G::$Cache->delete_value("user_info_$UserID");
            G::$Cache->delete_value("user_info_heavy_$UserID");
            G::$Cache->delete_value("user_stats_$UserID");

            G::$DB->query("
                SELECT SessionID
                FROM users_sessions
                WHERE UserID = '$UserID'
                    AND Active = 1");
            while (list($SessionID) = G::$DB->next_record()) {
                G::$Cache->delete_value("session_$UserID"."_$SessionID");
            }
            G::$Cache->delete_value("users_sessions_$UserID");

            G::$DB->query("
                DELETE FROM users_sessions
                WHERE UserID = '$UserID'");

        }

        // Remove the users from the tracker.
        G::$DB->query('
            SELECT torrent_pass
            FROM users_main
            WHERE ID in ('.implode(', ', $UserIDs).')');
        $PassKeys = G::$DB->collect('torrent_pass');
        $Concat = '';
        foreach ($PassKeys as $PassKey) {
            if (strlen($Concat) > 3950) { // Ocelot's read buffer is 4 KiB and anything exceeding it is truncated
                Tracker::update_tracker('remove_users', ['passkeys' => $Concat]);
                $Concat = $PassKey;
            } else {
                $Concat .= $PassKey;
            }
        }
        Tracker::update_tracker('remove_users', ['passkeys' => $Concat]);
        G::$DB->set_query_id($QueryID);
    }
}
