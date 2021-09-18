<?php

namespace Gazelle;

use \Gazelle\Util\Crypto;

class Session extends Base {

    static public function decode(string $cookie): array {
        return explode('|~|', Crypto::decrypt($cookie, ENCKEY)) ?? [];
    }

    protected array $sessions;
    protected int $userId;

    public function __construct(int $userId) {
        parent::__construct();
        $this->userId = $userId;
        $this->sessions = $this->loadSessions();
    }

    public function loadSessions(): array {
        if (($sessions = $this->cache->get_value('users_sessions_' . $this->userId)) === false) {
            $this->db->prepared_query("
                SELECT SessionID,
                    Browser,
                    OperatingSystem,
                    IP,
                    LastUpdate
                FROM users_sessions
                WHERE Active = 1
                    AND UserID = ?
                ORDER BY LastUpdate DESC
                ", $this->userId
            );
            $sessions = $this->db->to_array('SessionID', MYSQLI_ASSOC, false);
            $this->cache->cache_value('users_sessions_' . $this->userId, $sessions, 43200);
        }
        return $sessions;
    }

    public function valid(string $sessionId): bool {
        return isset($this->sessions[$sessionId]);
    }

    public function refresh(string $sessionId) {
        if (strtotime($this->sessions[$sessionId]['LastUpdate']) + 600 >= time()) {
            // Update every 10 minutes
            return;
        }
        $userAgent = parse_user_agent();

        $this->db->prepared_query("
            UPDATE user_last_access SET
                last_access = now()
            WHERE user_id = ?
            ", $this->userId
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
            ", $_SERVER['REMOTE_ADDR'], $userAgent['Browser'], $userAgent['BrowserVersion'],
                $userAgent['OperatingSystem'], $userAgent['OperatingSystemVersion'],
                $this->userId, $sessionId
        );
        $this->cache->delete_value('users_sessions_' . $this->userId);
        $this->sessions = $this->loadSessions();
    }

    public function create(array $info): array {
        $sessionId = randomString();
        $this->db->prepared_query('
            INSERT INTO users_sessions
                   (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, FullUA, LastUpdate)
            VALUES (?,      ?,         ?,          ?,       ?,               ?,  ?,      now())
            ', $this->userId, $sessionId, $info['keep-logged'], $info['browser'], $info['os'], $info['ipaddr'], $info['useragent']
        );

        $this->db->prepared_query('
            INSERT INTO user_last_access
                   (user_id, last_access)
            VALUES (?, now())
            ON DUPLICATE KEY UPDATE last_access = now()
            ', $this->userId
        );
        $this->cache->delete_value("users_sessions_" . $this->userId);
        $this->sessions = $this->loadSessions();
        return $this->sessions[$sessionId];
    }

    public function cookie(string $sessionId): string {
        return Crypto::encrypt(Crypto::encrypt($sessionId . '|~|' . $this->userId, ENCKEY), ENCKEY);
    }

    public function drop(string $sessionId): int {
        $this->db->prepared_query('
            DELETE FROM users_sessions
            WHERE UserID = ?  AND SessionID = ?
            ', $this->userId, $sessionId
        );
        $this->cache->deleteMulti([
            'session_' . $sessionId,
            'u_' . $this->userId,
            'users_sessions_' . $this->userId,
            'user_info_' . $this->userId,
            'user_info_heavy_' . $this->userId,
            'user_stats_' . $this->userId,
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
            ", $this->userId
        );
        $this->cache->deleteMulti(array_merge(
            $this->db->collect('ck'),
            [
                'u_' . $this->userId,
                'users_sessions_' . $this->userId,
                'user_info_' . $this->userId,
                'user_info_heavy_' . $this->userId,
                'user_stats_' . $this->userId,
            ]
        ));
        $this->db->prepared_query('
            DELETE FROM users_sessions WHERE UserID = ?
            ', $this->userId
        );
        return $this->db->affected_rows();
    }

    public function lastActive(string $sessionId): ?array {
        if (count($this->sessions) > 1) {
            foreach ($this->sessions as $id => $session) {
                if ($id != $sessionId) {
                    return $session;
                }
            }
        }
        return null;
    }
}
