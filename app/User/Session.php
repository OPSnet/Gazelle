<?php

namespace Gazelle\User;

use \Gazelle\Util\Crypto;

class Session extends \Gazelle\BaseUser {
    protected const CACHE_KEY = 'u_sess_%d';

    protected array $info = [];

    static public function decode(string $cookie): array {
        return explode('|~|', Crypto::decrypt($cookie, ENCKEY)) ?? [];
    }

    public function info(): array {
        if (!empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->user->id());
        $info = self::$cache->get_value($key);
        if ($info === false) {
            self::$db->prepared_query("
                SELECT SessionID,
                    Browser,
                    OperatingSystem,
                    IP,
                    LastUpdate
                FROM users_sessions
                WHERE Active = 1
                    AND UserID = ?
                ORDER BY LastUpdate DESC
                ", $this->user->id()
            );
            $info = self::$db->to_array('SessionID', MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $info, 43200);
        }
        $this->info = $info;
        return $this->info;
    }

    public function valid(string $sessionId): bool {
        return isset($this->info()[$sessionId]);
    }

    public function refresh(string $sessionId) {
        if (strtotime($this->info()[$sessionId]['LastUpdate']) + 600 >= time()) {
            // Update every 10 minutes
            return;
        }
        $userAgent = parse_user_agent();

        self::$db->prepared_query("
            UPDATE user_last_access SET
                last_access = now()
            WHERE user_id = ?
            ", $this->user->id()
        );
        self::$db->prepared_query("
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
                $this->user->id(), $sessionId
        );
        self::$cache->delete_value('users_sessions_' . $this->user->id());
        $this->info = [];
    }

    public function create(array $info): array {
        $sessionId = randomString();
        self::$db->prepared_query('
            INSERT INTO users_sessions
                   (UserID, SessionID, KeepLogged, Browser, OperatingSystem, IP, FullUA, LastUpdate)
            VALUES (?,      ?,         ?,          ?,       ?,               ?,  ?,      now())
            ', $this->user->id(), $sessionId, $info['keep-logged'], $info['browser'], $info['os'], $info['ipaddr'], $info['useragent']
        );

        self::$db->prepared_query('
            INSERT INTO user_last_access
                   (user_id, last_access)
            VALUES (?, now())
            ON DUPLICATE KEY UPDATE last_access = now()
            ', $this->user->id()
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->user->id()));
        return $this->info()[$sessionId];
    }

    public function cookie(string $sessionId): string {
        return Crypto::encrypt(Crypto::encrypt($sessionId . '|~|' . $this->user->id(), ENCKEY), ENCKEY);
    }

    public function drop(string $sessionId): int {
        self::$db->prepared_query('
            DELETE FROM users_sessions
            WHERE UserID = ?
                AND SessionID = ?
            ', $this->user->id(), $sessionId
        );
        self::$cache->deleteMulti([
            sprintf(self::CACHE_KEY, $this->user->id()),
            'session_' . $sessionId,
        ]);
        return self::$db->affected_rows();
    }

    public function dropAll(): int {
        self::$db->prepared_query("
            SELECT concat('session_', SessionID) AS ck
            FROM users_sessions
            WHERE UserID = ?
            ", $this->user->id()
        );
        self::$cache->deleteMulti([
            sprintf(self::CACHE_KEY, $this->user->id()),
            ...self::$db->collect('ck', false)
        ]);
        self::$db->prepared_query('
            DELETE FROM users_sessions WHERE UserID = ?
            ', $this->user->id()
        );
        return self::$db->affected_rows();
    }

    public function lastActive(string $sessionId): ?array {
        if (count($this->info) > 1) {
            foreach ($this->info as $id => $session) {
                if ($id != $sessionId) {
                    return $session;
                }
            }
        }
        return null;
    }
}
