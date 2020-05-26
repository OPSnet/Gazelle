<?php

namespace Gazelle;

class Session extends Base {

    private $id;

    public function __construct($id) {
        parent::__construct();
        $this->id = $id;
    }

    public function sessions() {
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
        return $sessions;
    }

    public function update($args) {
        $this->db->prepared_query('
            UPDATE user_last_access
            SET last_access = now()
            WHERE user_id = ?
            ', $this->id
        );
        $sessionId = $args['session-id'];
        $now = sqltime(); // keep db and cache synchronized
        $this->db->prepared_query('
            UPDATE users_sessions SET
                IP = ?, Browser = ?, BrowserVersion = ?,
                OperatingSystem = ?, OperatingSystemVersion = ?, LastUpdate = ?
            WHERE UserID = ? AND SessionID = ?
            ', $args['ip-address'], $args['browser'], $args['browser-version'],
                $args['os'], $args['os-version'], $now,
                /* where */ $this->id, $sessionId
        );
        $this->cache->begin_transaction('users_sessions_' . $this->id);
        $this->cache->delete_row($sessionId);
        $this->cache->insert_front($sessionId, [
            'SessionID'              => $sessionId,
            'IP'                     => $args['ip-address'],
            'Browser'                => $args['browser'],
            'BrowserVersion'         => $args['browser-version'],
            'OperatingSystem'        => $args['os'],
            'OperatingSystemVersion' => $args['os-version'],
            'LastUpdate'             => sqltime()
        ]);
        $this->cache->commit_transaction(0);
    }

    public function drop($sessionId) {
        $this->db->prepared_query('
            DELETE FROM users_sessions
            WHERE UserID = ?  AND SessionID = ?
            ', $this->id, $sessionId
        );
        $this->cache->begin_transaction('users_sessions_' . $this->id);
        $this->cache->delete_row($sessionId);
        $this->cache->commit_transaction(0);
    }

    public function dropAll() {
        $this->db->prepared_query('
            DELETE FROM users_sessions WHERE UserID = ?
            ', $this->id
        );
        $this->cache->delete_value('users_sessions_' . $this->id);
    }
}
