<?php

namespace Gazelle\Manager;

class ClientWhitelist extends \Gazelle\Base {
    const CACHE_KEY = 'whitelisted_clients';

    /**
     * Get the peer ID of client
     *
     * @param int $clientId The ID of the client
     * @return string The peer identifier
     */
    public function peerId(int $clientId) {
        return $this->db->scalar("
            SELECT peer_id
            FROM xbt_client_whitelist
            WHERE id = ?
            ", $clientId
        );
    }

    public function list() {
        if (($list = $this->cache->get_value(self::CACHE_KEY)) === false) {
            $this->db->prepared_query("
                SELECT id as client_id, vstring, peer_id
                FROM xbt_client_whitelist
                ORDER BY peer_id ASC
            ");
            $list = $this->db->to_array('client_id', MYSQLI_ASSOC);
            $this->cache->cache_value(self::CACHE_KEY, $list, 0);
        }
        return $list;
    }

     /**
      * Create a client
      *
      * @param string $peer The new peer identifier
      * @param string $vstring The new client vstring
      * @return string The new peer identifier (unchanged)
      */
     public function create(string $peer, string $vstring) {
        $this->db->prepared_query("
            INSERT INTO xbt_client_whitelist
                   (peer_id, vstring)
            VALUES (?,       ?)
            ", $peer, $vstring
        );
        $this->cache->delete_value(self::CACHE_KEY);
        return $peer;
    }

     /**
      * Modify a client
      *
      * @param int $clientId The ID of the client
      * @param string $peer The new peer identifier
      * @param string $vstring The new client vstring
      * @return string The previous peer identifier
      */
     public function modify(int $clientId, string $peer, string $vstring) {
        $prevPeer = $this->peerId($clientId);
        $this->db->prepared_query("
            UPDATE xbt_client_whitelist SET
                peer_id = ?,
                vstring = ?
            WHERE id = ?
            ", $peer, $vstring, $clientId
        );
        $this->cache->delete_value(self::CACHE_KEY);
        return $prevPeer . $this->db->affected_rows();
    }

    /**
     * Remove a client
     *
     * @param int $clientID The ID of the client
     * @return int 0/1 Whether a client was found
     */
    public function remove(int $clientId) {
        $this->db->prepared_query("
            DELETE FROM xbt_client_whitelist
            WHERE id = ?
            ", $clientId
        );
        $this->cache->delete_value(self::CACHE_KEY);
        return $this->db->affected_rows();
    }
}
