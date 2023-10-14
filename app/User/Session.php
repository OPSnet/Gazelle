<?php

namespace Gazelle\User;

use Gazelle\Util\Crypto;

class Session extends \Gazelle\BaseUser {
    final const tableName     = 'users_sessions';
    protected const CACHE_KEY = 'u_sess_%d';

    public function flush(): static { $this->user()->flush(); return $this; }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
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

    public function refresh(string $sessionId, string $ipaddr, array $browser): bool {
        if (strtotime($this->info()[$sessionId]['LastUpdate']) > time() - 600) {
            // Update every 10 minutes
            return false;
        }
        // Pages with ajax calls that need a session refresh will hit this
        // function multiple times simultaneously, thus producing excessive
        // contention on the user_last_access table. To get around this, we
        // do a cheap append to a delta table, and then reconsolidate to
        // the real table every once in a while via the scheduler.
        self::$db->prepared_query("
            INSERT INTO user_last_access_delta (user_id) VALUES (?)
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
            ", $ipaddr, $browser['Browser'], $browser['BrowserVersion'],
               $browser['OperatingSystem'], $browser['OperatingSystemVersion'],
               $this->user->id(), $sessionId
        );
        self::$cache->delete_value('users_sessions_' . $this->user->id());
        $this->info = [];
        return true;
    }

    public function create(array $info): array {
        $sessionId = randomString();
        self::$db->prepared_query('
            INSERT INTO users_sessions
                   (UserID, SessionID, KeepLogged, Browser, BrowserVersion, OperatingSystem, OperatingSystemVersion, IP, FullUA, LastUpdate)
            VALUES (?,      ?,         ?,          ?,       ?,              ?,               ?,                      ?,  ?,      now())
            ', $this->user->id(), $sessionId, $info['keep-logged'],
               $info['browser']['Browser'], $info['browser']['BrowserVersion'],
               $info['browser']['OperatingSystem'], $info['browser']['OperatingSystemVersion'],
               $info['ipaddr'], $info['useragent']
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
        return Crypto::encrypt($sessionId . '|~|' . $this->user->id(), ENCKEY);
    }

    public function drop(string $sessionId): int {
        self::$db->prepared_query('
            DELETE FROM users_sessions
            WHERE UserID = ?
                AND SessionID = ?
            ', $this->user->id(), $sessionId
        );
        self::$cache->delete_multi([
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
        self::$cache->delete_multi([
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
        $info = $this->info();
        if (count($info) > 1) {
            foreach ($info as $id => $session) {
                if ($id != $sessionId) {
                    return $session;
                }
            }
        }
        return null;
    }
}
