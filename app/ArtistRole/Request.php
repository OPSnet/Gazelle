<?php

namespace Gazelle\ArtistRole;

class Request extends \Gazelle\ArtistRole {
    protected array $artistList;

    protected function artistList(): array {
        if (!isset($this->artistList)) {
            self::$db->prepared_query("
                SELECT r.artist_role_id,
                    r.name      AS role_name,
                    ra.ArtistID AS artist_id,
                    aa.Name     AS name
                FROM requests_artists AS ra
                INNER JOIN artist_role r ON (r.artist_role_id = ra.Importance)
                INNER JOIN artists_alias AS aa USING (AliasID)
                WHERE ra.RequestID = ?
                ORDER BY r.artist_role_id ASC, aa.Name ASC
                ", $this->id
            );
            $this->artistList = self::$db->to_array(false, MYSQLI_ASSOC, false);
        }
        return $this->artistList;
    }

    /**
     * A cryptic representation of the artists grouped by their roles in a
     * release group. All artist roles are present as arrays (no need to see if
     * the key exists).
     * A role is an array of three keys: ["id" => 801, "name" => "The Group"]
     */
    public function idList(): array {
        if (!isset($this->artistList)) {
            $this->artistList = $this->artistList();
        }
        $list = [];
        foreach ($this->artistList as $artist) {
            $roleId = $artist['artist_role_id'];
            if (!isset($list[$roleId])) {
                $list[$roleId] = [];
            }
            $list[$roleId][] = [
                'id'   => $artist['artist_id'],
                'name' => $artist['name'],
            ];
        }
        return $list;
    }

    public function roleList(): array {
        if (!isset($this->artistList)) {
            $this->artistList = $this->artistList();
        }
        $list = [];
        foreach ($this->artistList as $artist) {
            $roleName = $artist['role_name'];
            if (!isset($list[$roleName])) {
                $list[$roleName] = [];
            }
            $list[$roleName][] = [
                'artist' => $this->manager->findById($artist['artist_id']),
                'id'     => $artist['artist_id'],
                'name'   => $artist['name'],
            ];
        }
        return $list;
    }
}
