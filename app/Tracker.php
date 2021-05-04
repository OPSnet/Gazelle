<?php
// TODO: The following actions are used, turn them into methods
// change_passkey
// add_token
// remove_token
// add_torrent
// delete_torrent
// update_torrent
// add_user
// update_user
// remove_user
// remove_users
// add_whitelist
// edit_whitelist
// remove_whitelist

namespace Gazelle;

use Gazelle\Util\Irc;

class Tracker {
    const STATS_MAIN = 0;
    const STATS_USER = 1;

    protected static $Requests = [];

    public function requestList(): array {
        return self::$Requests;
    }

    /**
     * Send a GET request over a socket directly to the tracker
     * For example, Tracker::update_tracker('change_passkey', array('oldpasskey' => OLD_PASSKEY, 'newpasskey' => NEW_PASSKEY)) will send the request:
     * GET /tracker_32_char_secret_code/update?action=change_passkey&oldpasskey=OLD_PASSKEY&newpasskey=NEW_PASSKEY HTTP/1.1
     *
     * @param string $Action The action to send
     * @param array $Updates An associative array of key->value pairs to send to the tracker
     * @param boolean $ToIRC Sends a message to the channel #tracker with the GET URL.
     */
    public function update_tracker($Action, $Updates, $ToIRC = false) {
        // Build request
        $Get = TRACKER_SECRET . "/update?action=$Action";
        foreach ($Updates as $Key => $Value) {
            $Get .= "&$Key=$Value";
        }

        $MaxAttempts = 3;
        $Err = false;
        if (self::send_request($Get, $MaxAttempts, $Err) === false) {
            Irc::sendRaw("PRIVMSG #tracker :$MaxAttempts $Err $Get");
            global $Cache;
            if ($Cache->get_value('ocelot_error_reported') === false) {
                Irc::sendRaw('PRIVMSG ' . ADMIN_CHAN . " :Failed to update ocelot: $Err : $Get");
                $Cache->cache_value('ocelot_error_reported', true, 3600);
            }
            return false;
        }
        return true;
    }

    /**
     * Get global peer stats from the tracker
     *
     * @return array(0 => $Leeching, 1 => $Seeding) or false if request failed
     */
    public function global_peer_count(): ?array {
        $Stats = self::get_stats(self::STATS_MAIN);
        if (isset($Stats['leechers tracked']) && isset($Stats['seeders tracked'])) {
            $Leechers = $Stats['leechers tracked'];
            $Seeders = $Stats['seeders tracked'];
        } else {
            return false;
        }
        return [$Leechers, $Seeders];
    }

    /**
     * Get peer stats for a user from the tracker
     *
     * @param string $TorrentPass The user's pass key
     * @return array(0 => $Leeching, 1 => $Seeding) or false if the request failed
     */
    public function user_peer_count(string $TorrentPass): ?array {
        $Stats = self::get_stats(self::STATS_USER, ['key' => $TorrentPass]);
        if ($Stats === false) {
            return false;
        }
        if (isset($Stats['leeching']) && isset($Stats['seeding'])) {
            $Leeching = $Stats['leeching'];
            $Seeding = $Stats['seeding'];
        } else {
            // User doesn't exist, but don't tell anyone
            $Leeching = $Seeding = 0;
        }
        return [$Leeching, $Seeding];
    }

    /**
     * Get whatever info the tracker has to report
     *
     * @return results from get_stats()
     */
    public function info() {
        return self::get_stats(self::STATS_MAIN);
    }

    /**
     * Send a stats request to the tracker and process the results
     *
     * @param int $Type Stats type to get
     * @param array $Params Parameters required by stats type
     * @return array with stats in named keys or empty if the request failed
     */
    private function get_stats($Type, $Params = false): array {
        if (!defined('TRACKER_REPORTKEY')) {
            return [];
        }
        $Get = TRACKER_REPORTKEY . '/report?';
        if ($Type === self::STATS_MAIN) {
            $Get .= 'get=stats';
        } elseif ($Type === self::STATS_USER && !empty($Params['key'])) {
            $Get .= "get=user&key={$Params['key']}";
        } else {
            return [];
        }
        $Response = self::send_request($Get);
        if ($Response === false) {
            return [];
        }
        $Stats = [];
        foreach (explode("\n", $Response) as $Stat) {
            list($Val, $Key) = explode(" ", $Stat, 2);
            $Stats[$Key] = $Val;
        }
        return $Stats;
    }

    /**
     * Send a request to the tracker
     *
     * @param string $Get GET string to send to the tracker
     * @param int $MaxAttempts Maximum number of failed attempts before giving up
     * @param bool $Err Variable to use as storage for the error string if the request fails
     * @return tracker response message or false if the request failed
     */
    private function send_request($Get, $MaxAttempts = 1, &$Err = false) {
        if (defined('DISABLE_TRACKER') && DISABLE_TRACKER === true) {
            return false;
        }
        $Header = "GET /$Get HTTP/1.1\r\nConnection: Close\r\n\r\n";
        $Attempts = 0;
        $Sleep = 0;
        $Success = false;
        $StartTime = microtime(true);
        $Data = "";
        $Response = "";
        while (!$Success && $Attempts++ < $MaxAttempts) {
            if ($Sleep) {
                sleep($Sleep);
            }
            $Sleep = 6;

            // Send request
            $File = fsockopen(TRACKER_HOST, TRACKER_PORT, $ErrorNum, $ErrorString);
            if ($File) {
                if (fwrite($File, $Header) === false) {
                    $Err = "Failed to fwrite()";
                    $Sleep = 3;
                    continue;
                }
            } else {
                $Err = "Failed to fsockopen() - $ErrorNum - $ErrorString";
                continue;
            }

            // Check for response.
            $Response = '';
            while (!feof($File)) {
                $Response .= fread($File, 1024);
            }
            $DataStart = strpos($Response, "\r\n\r\n") + 4;
            $DataEnd = strrpos($Response, "\n");
            if ($DataEnd > $DataStart) {
                $Data = substr($Response, $DataStart, $DataEnd - $DataStart);
            } else {
                $Data = "";
            }
            $Status = substr($Response, $DataEnd + 1);
            if ($Status == "success") {
                $Success = true;
            }
        }
        $Request = [
            'path' => substr($Get, strpos($Get, '/')),
            'response' => ($Success ? $Data : $Response),
            'status' => ($Success ? 'ok' : 'failed'),
            'time' => 1000 * (microtime(true) - $StartTime)
        ];
        self::$Requests[] = $Request;
        if ($Success) {
            return $Data;
        }
        return false;
    }
}
