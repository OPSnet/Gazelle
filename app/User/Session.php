<?php

namespace Gazelle\User;

use Gazelle\Util\Crypto;

class Session extends \Gazelle\BaseUser {
    final public const tableName = 'users_sessions';
    protected const CACHE_KEY    = 'u_sess_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->user->id));
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->user->id);
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
                ", $this->user->id
            );
            $info = self::$db->to_array('SessionID', MYSQLI_ASSOC);
            self::$cache->cache_value($key, $info, 43200);
        }
        $this->info = $info;
        return $this->info;
    }

    public function lastActive(?string $sessionKey): ?array {
        $info = $this->info();
        if (count($info) > 1) {
            foreach ($info as $id => $session) {
                if ($id != $sessionKey) {
                    return $session;
                }
            }
        }
        return null;
    }

    public function valid(string $sessionKey): bool {
        return isset($this->info()[$sessionKey]);
    }

    public function refresh(string $sessionKey): bool {
        if (!isset($this->info()[$sessionKey])) {
            return false;
        }
        if (time() - (int)strtotime($this->info()[$sessionKey]['LastUpdate']) < 600) {
            // Update every 10 minutes
            return false;
        }
        // Pages with ajax calls that need a session refresh will hit this
        // function multiple times simultaneously, thus producing excessive
        // contention on the user_last_access table. To get around this, we
        // do a cheap append to a delta table, and then reconsolidate to
        // the real table every once in a while via the scheduler.
        $this->user->refreshLastAccess();
        $ua = $this->requestContext()->ua();
        self::$db->prepared_query("
            UPDATE users_sessions SET
                LastUpdate = now(),
                IP = ?,
                Browser = ?,
                BrowserVersion = ?,
                OperatingSystem = ?,
                OperatingSystemVersion = ?
            WHERE UserID = ?
                AND SessionID = ?
            ", $this->requestContext()->remoteAddr(),
                $ua['Browser'], $ua['BrowserVersion'],
                $ua['OperatingSystem'], $ua['OperatingSystemVersion'],
                $this->user->id, $sessionKey
        );
        new History($this->user)
            ->registerSiteIp($this->requestContext()->remoteAddr());

        $this->flush();
        return true;
    }

    public function create(array $info): array {
        $sessionKey = randomString();
        self::$db->prepared_query('
            INSERT INTO users_sessions
                   (UserID, SessionID, KeepLogged, Browser, BrowserVersion, OperatingSystem, OperatingSystemVersion, IP, FullUA, LastUpdate)
            VALUES (?,      ?,         ?,          ?,       ?,              ?,               ?,                      ?,  ?,      now())
            ', $this->user->id, $sessionKey, $info['keep-logged'],
               $info['browser']['Browser'], $info['browser']['BrowserVersion'],
               $info['browser']['OperatingSystem'], $info['browser']['OperatingSystemVersion'],
               $info['ipaddr'], $info['useragent']
        );

        self::$db->prepared_query('
            INSERT INTO user_last_access
                   (user_id, last_access)
            VALUES (?, now())
            ON DUPLICATE KEY UPDATE last_access = now()
            ', $this->user->id
        );
        $this->flush();
        return $this->info()[$sessionKey];
    }

    public function cookie(string $sessionKey): string {
        return Crypto::encrypt($sessionKey . '|~|' . $this->user->id, ENCKEY);
    }

    public function drop(string $sessionKey): int {
        self::$db->prepared_query('
            DELETE FROM users_sessions
            WHERE UserID = ?
                AND SessionID = ?
            ', $this->user->id, $sessionKey
        );
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->user->id),
            'session_' . $sessionKey,
        ]);
        return self::$db->affected_rows();
    }

    public function dropAll(): int {
        self::$db->prepared_query("
            SELECT concat('session_', SessionID) AS ck
            FROM users_sessions
            WHERE UserID = ?
            ", $this->user->id
        );
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->user->id),
            ...self::$db->collect('ck')
        ]);
        self::$db->prepared_query('
            DELETE FROM users_sessions WHERE UserID = ?
            ', $this->user->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Log out the current session
     */
    public function logout(): void {
        setcookie('session', '', [
            'expires'  => time() - 60 * 60 * 24 * 90,
            'path'     => '/',
            'secure'   => SECURE_COOKIE,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        if ($this->requestContext()->hasSessionId()) {
            $this->drop($this->requestContext()->sessionId());
        }
        $this->flush();
    }

    /**
     * Logout all sessions
     */
    public function logoutEverywhere(): void {
        $this->dropAll();
        $this->logout();
    }
}
