<?php

namespace Gazelle\Manager;

class ClientWhitelist extends \Gazelle\Base {
    final const CACHE_KEY = 'whitelisted_clients';

     /**
      * Create a client
      */
    public function create(string $peer, string $vstring): int {
        self::$db->prepared_query("
            INSERT INTO xbt_client_whitelist
                   (peer_id, vstring)
            VALUES (?,       ?)
            ", $peer, $vstring
        );
        $id = self::$db->inserted_id();
        self::$cache->delete_value(self::CACHE_KEY);
        return $id;
    }

    /**
     * Get the public peer ID of table ID
     */
    public function peerId(int $clientId): string {
        return (string)self::$db->scalar("
            SELECT peer_id
            FROM xbt_client_whitelist
            WHERE id = ?
            ", $clientId
        );
    }

    public function list(): array {
        $list = self::$cache->get_value(self::CACHE_KEY);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT id as client_id, vstring, peer_id
                FROM xbt_client_whitelist
                ORDER BY peer_id ASC
            ");
            $list = self::$db->to_array('client_id', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::CACHE_KEY, $list, 0);
        }
        return $list;
    }

     /**
      * Modify a client
      *
      * @return string The previous peer identifier
      */
    public function modify(int $clientId, string $peer, string $vstring): string {
        $prevPeer = $this->peerId($clientId);
        self::$db->prepared_query("
            UPDATE xbt_client_whitelist SET
                peer_id = ?,
                vstring = ?
            WHERE id = ?
            ", $peer, $vstring, $clientId
        );
        self::$cache->delete_value(self::CACHE_KEY);
        return $prevPeer;
    }

    /**
     * Remove a client
     */
    public function remove(int $clientId): int {
        self::$db->prepared_query("
            DELETE FROM xbt_client_whitelist
            WHERE id = ?
            ", $clientId
        );
        self::$cache->delete_value(self::CACHE_KEY);
        return self::$db->affected_rows();
    }
}
