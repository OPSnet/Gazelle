<?php

declare(strict_types=1);

namespace Gazelle\ArtistRole;

class Request extends \Gazelle\ArtistRole {
    protected function cacheKey(): string {
        return sprintf('ar_request_2_%d', $this->object->id());
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
        self::$cache->delete_multi([
            "request_artists_{$this->object->id()}",
            ...$this->pg()->columnex(<<<END_SQL
                select 'artists_requests_' || aa."ArtistID"
                from request_artist            ra
                inner join relay.artists_alias aa on (aa."AliasID" = ra.id_alias)
                where ra.id_request = $1
                group by aa."ArtistID"
                END_SQL
                , $this->object->id()
            ),
        ]);
        $this->pg()->query("
            delete from request_artist where id_request = $1
            ", $this->object->id()
        );

        // and (re)create
        $affected = 0;
        foreach ($roleList as $role => $artistList) {
            foreach ($artistList as $artist) {
                $affected += $this->pg()->query("
                    insert into request_artist
                           (id_request, id_alias, id_artist_role, id_user)
                    VALUES ($1,         $2,       $3,             $4)
                    ", $this->object->id(), $artist->aliasId(), $role, $user->id
                );
                self::$cache->delete_value("artists_requests_{$artist->id}");
            }
        }
        $this->pg()->query(<<<END_SQL
            update request set
                artist_title_ts = upd.artist_title_ts
            from (
                select r.id_request,
                    to_tsvector('simple', coalesce(string_agg(aa."Name", ' '), '')
                        || ' ' || r.title
                    ) as artist_title_ts
                from request                  r
                left join request_artist      ra using (id_request)
                left join artist_role         ar using (id_artist_role)
                left join relay.artists_alias aa on (aa."AliasID" = ra.id_alias)
                where (ar.slug is null or ar.slug != 'guest')
                    and r.id_request = $1
                group by r.id_request
            ) upd
            where request.id_request = upd.id_request
            END_SQL
            , $this->object->id()
        );
        $this->pg()->commit();
        self::$db->commit();
        $this->flush();
        return $affected;
    }

    protected function artistListRaw(): array {
        return $this->pg()->allex(<<<END_SQL
            select ra.id_artist_role,
                ar.slug,
                aa."ArtistID" as artist_id,
                aa."AliasID"  as alias_id,
                aa."Name"     as name
            from request                   r
            inner join request_artist      ra using (id_request)
            inner join artist_role         ar using (id_artist_role)
            inner join relay.artists_alias aa on (aa."AliasID" = ra.id_alias)
            where r.id_request = $1
            order by ar.id_artist_role ASC, aa."Name" ASC
            END_SQL
            , $this->object->id()
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
            $roleId = $artist['id_artist_role'];
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
            $roleId = $artist['id_artist_role'];
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
