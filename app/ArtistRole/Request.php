<?php

namespace Gazelle\ArtistRole;

class Request extends \Gazelle\ArtistRole {
    /**
     * Create or modify the set of artists associated with a request
     */
    public function set(array $roleList, \Gazelle\Manager\Artist $manager): int {
        self::$db->begin_transaction();
        foreach ($roleList as $role => $artistList) {
            foreach ($artistList as $n => $name) {
                // 1. See if each artist given already exists and if it does, grab the ID.
                self::$db->prepared_query("
                    SELECT ArtistID,
                        AliasID,
                        Name,
                        Redirect
                    FROM artists_alias
                    WHERE Name = ?
                    ", $name
                );
                while ([$artistId, $aliasId, $aliasName, $redirect] = self::$db->next_record(MYSQLI_NUM, false)) {
                    if (!strcasecmp($name, $aliasName)) {
                        if ($redirect) {
                            $aliasId = $redirect;
                        }
                        $roleList[$role][$n] = [
                            'id'      => $artistId,
                            'aliasid' => $aliasId,
                            'name'    => $aliasName,
                        ];
                        break;
                    }
                }
                if (!$artistId) {
                    // 2. For each artist that didn't exist, create an artist.
                    [$artistId, $aliasId] = $manager->create($name);
                    $roleList[$role][$n] = [
                        'id'      => $artistId,
                        'aliasid' => $aliasId,
                        'name'    => $name
                    ];
                }
            }
        }

        // remove any trace of the previous artistRole if we are updating
        self::$db->prepared_query("
            SELECT concat('artists_requests_', ArtistID) FROM requests_artists WHERE RequestID = ?
            ", $this->id
        );
        self::$cache->delete_multi([
            "request_artists_{$this->id}",
            ...self::$db->collect(0, false)
        ]);
        self::$db->prepared_query("
            DELETE FROM requests_artists WHERE RequestID = ?
            ", $this->id
        );

        // and (re)create
        $affected = 0;
        foreach ($roleList as $role => $artistList) {
            foreach ($artistList as $artist) {
                self::$db->prepared_query("
                    INSERT INTO requests_artists
                           (RequestID, ArtistID, AliasID, artist_role_id, Importance)
                    VALUES (?,         ?,        ?,       ?,              ?)
                    ", $this->id, $artist['id'], $artist['aliasid'], $role, (string)$role
                );
                $affected += self::$db->affected_rows();
                self::$cache->delete_value("artists_requests_{$artist['id']}");
            }
        }
        self::$db->commit();
        return $affected;
    }

    protected function artistListQuery(): \mysqli_result|bool {
        return self::$db->prepared_query("
            SELECT r.artist_role_id,
                r.slug      AS slug,
                ra.ArtistID AS artist_id,
                aa.AliasID  AS alias_id,
                aa.Name     AS name
            FROM requests_artists AS ra
            INNER JOIN artist_role r ON (r.artist_role_id = ra.Importance)
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ra.RequestID = ?
            ORDER BY r.artist_role_id ASC, aa.Name ASC
            ", $this->id
        );
    }

    /**
     * A cryptic representation of the artists grouped by their roles in a
     * release group. All artist roles are present as arrays (no need to see if
     * the key exists).
     * A role is an array of three keys: ["id" => 801, "aliasid" => 768, "name" => "The Group"]
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
                'id'      => $artist['artist_id'],
                'aliasid' => $artist['alias_id'],
                'name'    => $artist['name'],
            ];
        }
        return $list;
    }

    public function roleNameList(): array {
        if (!isset($this->artistList)) {
            $this->artistList = $this->artistList();
        }
        $list = [];
        foreach ($this->artistList as $artist) {
            $roleId = $artist['artist_role_id'];
            if (!isset($list[$roleId])) {
                $list[$roleId] = [];
            }
            $list[$roleId][] = $artist['name'];
        }
        return $list;
    }

    public function nameList(): array {
        $list = [];
        foreach ($this->idList() as $artistList) {
            foreach ($artistList as $artist) {
                $list[$artist['name']] = true;
            }
        }
        return array_keys($list);
    }

    public function roleList(): array {
        if (!isset($this->artistList)) {
            $this->artistList = $this->artistList();
        }
        $list = [];
        foreach ($this->artistList as $artist) {
            $roleName = $artist['slug'];
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
