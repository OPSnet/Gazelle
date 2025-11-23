<?php

declare(strict_types=1);

namespace Gazelle\ArtistRole;

class Request extends \Gazelle\ArtistRole {
    protected function cacheKey(): string {
        return sprintf('ar_request_%d', $this->object->id());
    }

    /**
     * Create or modify the set of artists associated with a request
     */
    public function set(
        array                   $roleList,
        \Gazelle\User           $user,
        \Gazelle\Manager\Artist $manager = new \Gazelle\Manager\Artist(),
    ): int {
        self::$db->begin_transaction();
        $this->pg()->begin();
        foreach ($roleList as $role => $artistList) {
            foreach ($artistList as $n => $name) {
                $artist = $manager->findByName($name) ?? $manager->create($name);
                $roleList[$role][$n] = $artist;
            }
        }

        // remove any trace of the previous artistRole if we are updating
        self::$db->prepared_query("
            SELECT concat('artists_requests_', aa.ArtistID)
            FROM requests_artists ra
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE ra.RequestID = ?
            GROUP BY aa.ArtistID
            ", $this->object->id()
        );
        self::$cache->delete_multi([
            "request_artists_{$this->object->id()}",
            ...self::$db->collect(0)
        ]);
        self::$db->prepared_query("
            DELETE FROM requests_artists WHERE RequestID = ?
            ", $this->object->id()
        );
        $this->pg()->prepared_query("
            delete from request_artist where id_request = ?
            ", $this->object->id()
        );

        // and (re)create
        $affected = 0;
        foreach ($roleList as $role => $artistList) {
            foreach ($artistList as $artist) {
                self::$db->prepared_query("
                    INSERT INTO requests_artists
                           (RequestID, UserID, AliasID, artist_role_id)
                    VALUES (?,         ?,      ?,       ?)
                    ", $this->object->id(), $user->id, $artist->aliasId(), $role
                );
                $affected += self::$db->affected_rows();
                $this->pg()->prepared_query("
                    insert into request_artist
                           (id_request, id_alias, id_artist_role, id_user)
                    VALUES (?,         ?,      ?,       ?)
                    ", $this->object->id(), $artist->aliasId(), $role, $user->id
                );
                self::$cache->delete_value("artists_requests_{$artist->id}");
            }
        }
        $this->pg()->prepared_query("
            update request set
                artist_title_ts = upd.artist_title_ts
            from (
                select r.id_request,
                    to_tsvector('simple', coalesce(string_agg(aa.\"Name\", ' '), '')
                        || ' ' || r.title
                    ) as artist_title_ts
                from request r
                left join relay.requests_artists ra on (ra.\"RequestID\" = r.id_request)
                left join relay.artists_alias aa using (\"AliasID\")
                left join relay.artist_role ar on (ar.artist_role_id = ra.artist_role_id)
                where (ar.slug is null or ar.slug != 'guest')
                    and r.id_request = ?
                group by r.id_request
            ) upd
            where request.id_request = upd.id_request
            ", $this->object->id()
        );
        $this->pg()->commit();
        self::$db->commit();
        $this->flush();
        return $affected;
    }

    protected function artistListRaw(): array {
        self::$db->prepared_query("
            SELECT r.artist_role_id,
                r.slug      AS slug,
                aa.ArtistID AS artist_id,
                aa.AliasID  AS alias_id,
                aa.Name     AS name
            FROM requests_artists    ra
            INNER JOIN artist_role   r  USING (artist_role_id)
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE ra.RequestID = ?
            ORDER BY r.artist_role_id ASC, aa.Name ASC
            ", $this->object->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
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
