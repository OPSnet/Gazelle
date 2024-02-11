<?php
// TODO: The following actions are used, turn them into methods
// remove_users

namespace Gazelle;

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;
use Gazelle\Util\Irc;

class Tracker extends Base {
    final public const STATS_MAIN = 0;
    final public const STATS_USER = 1;

    protected static array $Requests = [];
    protected string|false $error    = false;

    public function requestList(): array {
        return self::$Requests;
    }

    public function last_error(): string|false {
        return $this->error;
    }

    public function addToken(Torrent $torrent, User $user): bool {
        return $this->update_tracker('add_token', [
            'info_hash' => $torrent->infohashEncoded(),
            'userid'    => $user->id(),
        ]);
    }

    public function removeToken(Torrent $torrent, User $user): bool {
        return $this->update_tracker('remove_token', [
            'info_hash' => $torrent->infohashEncoded(),
            'userid'    => $user->id(),
        ]);
    }

    public function addTorrent(Torrent $torrent): bool {
        return $this->update_tracker('add_torrent', [
            'info_hash'   => $torrent->flush()->infohashEncoded(),
            'id'          => $torrent->id(),
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

    public function addUser(User $user): bool {
        self::$cache->increment('stats_user_count');
        return $this->update_tracker('add_user', [
            'passkey' => $user->announceKey(),
            'id'      => $user->id(),
            'visible' => $user->isVisible() ? '1' : '0',
        ]);
    }

    public function refreshUser(User $user): bool {
        return $this->update_tracker('update_user', [
            'passkey'   => $user->announceKey(),
            'can_leech' => $user->canLeech() ? '1' : '0',
            'visible'   => $user->isVisible() ? '1' : '0',
        ]);
    }

    public function removeUser(User $user): bool {
        return $this->update_tracker('remove_user', [
            'passkey' => $user->announceKey(),
        ]);
    }

    public function addWhitelist(string $peer): bool {
        return $this->update_tracker('add_whitelist', [
            'peer_id' => $peer,
        ]);
    }

    public function modifyWhitelist(string $old, string $new): bool {
        return $this->update_tracker('edit_whitelist', [
            'old_peer_id' => $old,
            'new_peer_id' => $new,
        ]);
    }

    public function removeWhitelist(string $peer): bool {
        return $this->update_tracker('remove_whitelist', [
            'peer_id' => $peer,
        ]);
    }

    public function modifyAnnounceInterval(int $interval): bool {
        return $this->update_tracker('update_announce_interval', [
            'new_announce_interval' => $interval,
        ]);
    }

    public function modifyAnnounceJitter(int $interval): bool {
        return $this->update_tracker('update_announce_jitter', [
            'new_announce_jitter' => $interval,
        ]);
    }

    public function expireFreeleechTokens(string $payload): int {
        $clear = [];
        $expire = [];
        foreach (explode(',', $payload) as $item) {
            [$userId, $torrentId] = array_map('intval', explode(':', $item));
            if ($userId && $torrentId) {
                $expire[] = [$userId, $torrentId];
                $clear[$userId] = true;
            }
        }

        if (!$expire) {
            return 0;
        }
        self::$db->begin_transaction();
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE expire_freeleech (
                UserID int NOT NULL,
                TorrentID int NOT NULL,
                PRIMARY KEY (UserID, TorrentID)
            )
        ");
        self::$db->prepared_query("
            INSERT IGNORE INTO expire_freeleech (UserID, TorrentID) VALUES
            " . placeholders($expire, '(?, ?)')
            , ...array_merge(...$expire)
        );
        self::$db->prepared_query("
            UPDATE users_freeleeches uf
            INNER JOIN expire_freeleech ef USING (UserID, TorrentID)
            SET
                Expired = true
            WHERE
                Expired = false
        ");
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            DROP TABLE IF EXISTS expire_freeleech
        ");
        self::$db->commit();
        if (DEBUG_TRACKER_TOKEN_EXPIRE) {
            $filename = (string)DEBUG_TRACKER_TOKEN_EXPIRE; // phpstan, grrr
            $out = fopen($filename, 'a');
            if ($out !== false) {
                fprintf($out, "%s u=%d t=%d s=%s\n",
                    date('Y-m-d H:i:s'),
                    count($clear),
                    count($expire),
                    $payload,
                );
                fclose($out);
            }
        }
        self::$cache->delete_multi(array_map(fn($id) => "users_tokens_$id", array_keys($clear)));
        return $affected;
    }

    /**
     * Send a GET request over a socket directly to the tracker
     * For example, Tracker::update_tracker('change_passkey', array('oldpasskey' => OLD_PASSKEY, 'newpasskey' => NEW_PASSKEY)) will send the request:
     * GET /tracker_32_char_secret_code/update?action=change_passkey&oldpasskey=OLD_PASSKEY&newpasskey=NEW_PASSKEY HTTP/1.1
     */
    public function update_tracker(string $Action, array $Updates, bool $ToIRC = false): bool {
        if (DISABLE_TRACKER) {
            return true;
        }
        // Build request
        $Get = TRACKER_SECRET . "/update?action=$Action";
        foreach ($Updates as $Key => $Value) {
            $Get .= "&$Key=$Value";
        }

        $MaxAttempts = 3;
        $this->error = false;
        if ($this->send_request($Get, $MaxAttempts) === false) {
            Irc::sendMessage('#tracker', "$MaxAttempts $Get {$this->error}");
            global $Cache;
            if ($Cache->get_value('ocelot_error_reported') === false) {
                Irc::sendMessage(IRC_CHAN_DEV, "Failed to update ocelot: {$this->error} : $Get");
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
        $Stats = $this->report(self::STATS_MAIN);
        if (isset($Stats['leechers tracked']) && isset($Stats['seeders tracked'])) {
            $Leechers = $Stats['leechers tracked'];
            $Seeders = $Stats['seeders tracked'];
        } else {
            return false;
        }
        return [$Leechers, $Seeders];
    }

    /**
     * Get user context from the tracker
     */
    public function userReport(User $user): array {
        return $this->report(self::STATS_USER, ['key' => $user->announceKey()]);
    }

    /**
     * Get whatever info the tracker has to report
     */
    public function info(): array {
        return $this->report(self::STATS_MAIN);
    }

    /**
     * Send a stats request to the tracker and process the results
     *
     * @return array with stats in named keys or empty if the request failed
     */
    protected function report(int $Type, false|array $Params = false): array {
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
        if ($Response === false || $Response === "") {
            return [];
        }
        if ($Type === self::STATS_USER) {
            return json_decode($Response, true);
        }
        $Stats = [];
        foreach (explode("\n", $Response) as $Stat) {
            if (preg_match('/^(Uptime|version): (.*)$/', $Stat, $match)) {
                $Stats[strtolower($match[1])] = $match[2];
            } else {
                [$Val, $Key] = explode(" ", $Stat, 2);
                $Stats[$Key] = (int)$Val;
            }
        }
        return $Stats;
    }

    /**
     * Send a request to the tracker
     *
     * @return false|string tracker response message or false if the request failed
     */
    protected function send_request(string $Get, int $MaxAttempts = 1): false|string {
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
        $code = 0;
        while (!$Success && $Attempts++ < $MaxAttempts) {
            if ($Sleep) {
                usleep((int)$Sleep);
            }
            $Sleep = 1200000; // 1200ms

            // Send request
            $File = fsockopen(TRACKER_HOST, TRACKER_PORT, $ErrorNum, $ErrorString);
            if ($File) {
                if (fwrite($File, $Header) === false) {
                    $this->error = "Failed to fwrite()";
                    $Sleep *= 1.5; // exponential backoff
                    continue;
                }
            } else {
                $this->error = "Failed to fsockopen(" . TRACKER_HOST . ":" . TRACKER_PORT . ") - $ErrorNum - $ErrorString";
                continue;
            }

            // Check for response.
            $Response = '';
            while (!feof($File)) {
                $Response .= fread($File, 1024);
            }
            if (preg_match('/HTTP\/1.1 (\d+)/', $Response, $match)) {
                $code = $match[1];
            } else {
                break;
            }
            $DataStart = strpos($Response, "\r\n\r\n") + 4;
            $DataEnd = strrpos($Response, "\n");
            if ($DataEnd > $DataStart) {
                $Data = substr($Response, $DataStart, $DataEnd - $DataStart);
            } else {
                $Data = "";
            }
            $Status = substr($Response, $DataEnd + 1);
            if ($code == 200 || $Status == "success") {
                $Success = true;
            }
        }
        $path_array = explode("/", $Get, 2);
        $Request = [
            'path' => array_pop($path_array), // strip authkey from path
            'response' => ($Success ? $Data : $Response),
            'code' => $code,
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
