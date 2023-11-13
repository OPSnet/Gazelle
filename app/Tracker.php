<?php
// TODO: The following actions are used, turn them into methods
// add_token
// remove_token
// add_torrent DONE
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

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;
use Gazelle\Util\Irc;

class Tracker {
    final const STATS_MAIN = 0;
    final const STATS_USER = 1;

    protected static array $Requests = [];

    public function requestList(): array {
        return self::$Requests;
    }

    public function addTorrent(Torrent $torrent): bool {
        return $this->update_tracker('add_torrent', [
            'id'          => $torrent->id(),
            'info_hash'   => $torrent->flush()->infohashEncoded(),
            'freetorrent' => 0,
        ]);
    }

    public function modifyTorrent(TorrentAbstract $torrent, LeechType $leechType): bool {
        return $this->update_tracker('update_torrent', [
            'info_hash'   => $torrent->infohashEncoded(),
            'freetorrent' => $leechType->value
        ]);
    }

    public function modifyPasskey(string $old, string $new): bool {
        return $this->update_tracker('change_passkey', [
            'oldpasskey' => $old,
            'newpasskey' => $new,
        ]);
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
    public function update_tracker($Action, $Updates, $ToIRC = false): bool {
        if (DISABLE_TRACKER) {
            return true;
        }
        // Build request
        $Get = TRACKER_SECRET . "/update?action=$Action";
        foreach ($Updates as $Key => $Value) {
            $Get .= "&$Key=$Value";
        }

        $MaxAttempts = 3;
        $Err = false;
        if ($this->send_request($Get, $MaxAttempts, $Err) === false) {
            Irc::sendMessage('#tracker', "$MaxAttempts $Err $Get");
            global $Cache;
            if ($Cache->get_value('ocelot_error_reported') === false) {
                Irc::sendMessage(ADMIN_CHAN, "Failed to update ocelot: $Err : $Get");
                $Cache->cache_value('ocelot_error_reported', true, 3600);
            }
            return false;
        }
        return true;
    }

    /**
     * Get global peer stats from the tracker
     *
     * @return array|false (0 => $Leeching, 1 => $Seeding) or false if request failed
     */
    public function global_peer_count(): array|false {
        $Stats = $this->get_stats(self::STATS_MAIN);
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
     * @return array (0 => $Leeching, 1 => $Seeding)
     */
    public function user_peer_count(string $TorrentPass): array {
        $Stats = $this->get_stats(self::STATS_USER, ['key' => $TorrentPass]);
        if (empty($Stats)) {
            return [0, 0];
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
     * @return array results from get_stats()
     */
    public function info() {
        return $this->get_stats(self::STATS_MAIN);
    }

    /**
     * Send a stats request to the tracker and process the results
     *
     * @param int $Type Stats type to get
     * @param false|array $Params Parameters required by stats type
     * @return array with stats in named keys or empty if the request failed
     */
    private function get_stats($Type, false|array $Params = false): array {
        if (DISABLE_TRACKER) {
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
        $Response = $this->send_request($Get);
        if ($Response === false) {
            return [];
        }
        $Stats = [];
        foreach (explode("\n", $Response) as $Stat) {
            [$Val, $Key] = explode(" ", $Stat, 2);
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
     * @return false|string tracker response message or false if the request failed
     */
    private function send_request($Get, $MaxAttempts = 1, &$Err = false): false|string {
        if (DISABLE_TRACKER) {
            return false;
        }
        $Header = "GET /$Get HTTP/1.1\r\nHost: " . TRACKER_NAME . "\r\nConnection: Close\r\n\r\n";
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
        $path_array = explode("/", $Get, 2);
        $Request = [
            'path' => array_pop($path_array), // strip authkey from path
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
