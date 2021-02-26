<?php

namespace Gazelle;

use \Gazelle\Util\Crypto;

class Session extends Base {

    private $id;

    public function __construct($id) {
        parent::__construct();
        $this->id = $id;
    }

    public function sessions(): array {
        if (($sessions = $this->cache->get_value('users_sessions_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT
                    SessionID,
                    Browser,
                    OperatingSystem,
                    IP,
                    LastUpdate
                FROM users_sessions
                WHERE Active = 1
                    AND UserID = ?
                ORDER BY LastUpdate DESC
                ", $this->id
            );
            $sessions = $this->db->to_array('SessionID', MYSQLI_ASSOC);
            $this->cache->cache_value('users_sessions_' . $this->id, $sessions, 43200);
        }
        return $sessions ?: [];
    }

    public function create(array $info): array {
        $sessionId = randomString();
        $this->db->prepared_query('
            INSERT INTO users_sessions
                   (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, FullUA, LastUpdate)
            VALUES (?,      ?,         ?,          ?,       ?,               ?,  ?,      now())
            ', $this->id, $sessionId, $info['keep-logged'], $info['browser'], $info['os'], $info['ipaddr'], $info['useragent']
        );

        $this->db->prepared_query('
            INSERT INTO user_last_access
                   (user_id, last_access)
            VALUES (?, now())
            ON DUPLICATE KEY UPDATE last_access = now()
            ', $this->id
        );
        $this->cache->delete_value("users_sessions_" . $this->id);
        $sessions = $this->sessions();
        return $sessions[$sessionId];
    }

    public function cookie(string $sessionId): string {
        return Crypto::encrypt(Crypto::encrypt($sessionId . '|~|' . $this->id, ENCKEY), ENCKEY);
    }

    public function update(array $info): array {
        $this->db->prepared_query("
            UPDATE user_last_access SET
                last_access = now()
            WHERE user_id = ?
            ", $this->id
        );
        $this->db->prepared_query("
            UPDATE users_sessions SET
                LastUpdate = now(),
                IP = ?,
                Browser = ?,
                BrowserVersion = ?,
                OperatingSystem = ?,
                OperatingSystemVersion = ?
            WHERE UserID = ? AND SessionID = ?
            ", $info['ip-address'], $info['browser'], $info['browser-version'], $info['os'], $info['os-version'],
                /* where */ $this->id, $info['session-id']
        );
        $this->cache->delete_value('users_sessions_' . $this->id);
        return $this->sessions();
    }

    public function drop(string $sessionId): int {
        $this->db->prepared_query('
            DELETE FROM users_sessions
            WHERE UserID = ?  AND SessionID = ?
            ', $this->id, $sessionId
        );
        $this->cache->deleteMulti([
            'session_' . $sessionId,
            'u_' . $this->id,
            'users_sessions_' . $this->id,
            'user_info_' . $this->id,
            'user_info_heavy_' . $this->id,
            'user_stats_' . $this->id,
            'enabled_' . $this->id,
        ]);
        return $this->db->affected_rows();
    }

    public function purgeDead(): int {
        $this->db->prepared_query("
            SELECT concat('users_sessions_', UserID) as ck
            FROM users_sessions
            WHERE (LastUpdate < (now() - INTERVAL 30 DAY) AND KeepLogged = '1')
               OR (LastUpdate < (now() - INTERVAL 60 MINUTE) AND KeepLogged = '0')
        ");
        if (!$this->db->has_results()) {
            return 0;
        }
        $cacheKeys = $this->db->collect('ck', false);
        $this->db->prepared_query("
            DELETE FROM users_sessions
            WHERE (LastUpdate < (now() - INTERVAL 30 DAY) AND KeepLogged = '1')
               OR (LastUpdate < (now() - INTERVAL 60 MINUTE) AND KeepLogged = '0')
        ");
        $this->cache->deleteMulti($cacheKeys);
        return count($cacheKeys);
    }

    public function dropAll(): int {
        $this->db->prepared_query("
            SELECT concat('session_', SessionID) AS ck
            FROM users_sessions
            WHERE UserID = ?
            ", $this->id
        );
        $this->cache->deleteMulti(array_merge(
            $this->db->collect('ck'),
            [
                'u_' . $this->id,
                'users_sessions_' . $this->id,
                'user_info_' . $this->id,
                'user_info_heavy_' . $this->id,
                'user_stats_' . $this->id,
                'enabled_' . $this->id,
            ]
        ));
        $this->db->prepared_query('
            DELETE FROM users_sessions WHERE UserID = ?
            ', $this->id
        );
        return $this->db->affected_rows();
    }
}
