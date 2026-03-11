<?php

declare(strict_types=1);

namespace Gazelle\ArtistRole;

class TGroup extends \Gazelle\ArtistRole {
    protected function cacheKey(): string {
        return sprintf('ar_tgroup_2_%d', $this->object->id());
    }

    protected const MAP = [
        1 => 'main',
        2 => 'guest',
        3 => 'remixer',
        4 => 'composer',
        5 => 'conductor',
        6 => 'dj',
        7 => 'producer',
        8 => 'arranger',
    ];

    protected function artistListRaw(): array {
        self::$db->prepared_query("
            SELECT ta.artist_role_id as id_artist_role,
                aa.ArtistID AS artist_id,
                aa.Name     AS name,
                ta.AliasID  AS alias_id
            FROM torrents_artists    ta
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE ta.GroupID = ?
            ORDER BY ta.GroupID, ta.artist_role_id ASC, aa.Name ASC
            ", $this->object->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    protected function init(): void {
        $this->artistList = $this->artistList();
        $this->roleList = array_fill_keys(array_values(self::MAP), []);
        $this->idList = [];
        foreach ($this->artistList as $artist) {
            $roleId = $artist['id_artist_role'];
            $this->idList[$roleId][] = [
                'id'      => $artist['artist_id'],
                'aliasid' => $artist['alias_id'],
                'artist'  => $this->manager->findByAliasId($artist['alias_id']),
                'name'    => $artist['name'],
            ];
            $this->roleList[self::MAP[$roleId]][] = [
                'id'      => $artist['artist_id'],
                'aliasid' => $artist['alias_id'],
                'artist'  => $this->manager->findByAliasId($artist['alias_id']),
                'name'    => $artist['name'],
            ];
        }
    }

    /**
     * A readable representation of the artists grouped by their roles in a
     * release group. All artist roles are present as arrays (no need to see if
     * the key exists).
     * A role is an array of three keys: ["id" => 801, "aliasid" => 768, "name" => "The Group"]
     *
     * @return array ["main" => [["id" => 801, "aliasid" => 768, "name" => "The Group"]],
     *  "guest" => [], "remixer" => [], "composer" => [], "conductor" => [], "dj" => [],
     *  "producer" => [], "arranger" => [] ]
     */
    public function roleList(): array {
        if (!isset($this->roleList)) {
            $this->init();
        }
        return $this->roleList;
    }

    /**
     * A readable representation of the artists grouped by their roles in a
     * release group. All artist roles are present as arrays (no need to see if
     * the key exists). Like roleList() but some of the key names change.
     *   'main'     becomes 'artists'
     *   'guest'    becomes 'with'
     *   'remixer'  becomes 'remixedBy'
     *   'composer' becomes 'composers'
     * A role is an array of two keys: ["id" => 801, "name" => "The Group"]
     */
    public function roleListByType(): array {
        $list = $this->idList();
        return [
            'artists'   => $list[ARTIST_MAIN] ?? [],
            'with'      => $list[ARTIST_GUEST] ?? [],
            'remixedBy' => $list[ARTIST_REMIXER] ?? [],
            'composers' => $list[ARTIST_COMPOSER] ?? [],
            'conductor' => $list[ARTIST_CONDUCTOR] ?? [],
            'dj'        => $list[ARTIST_DJ] ?? [],
            'producer'  => $list[ARTIST_PRODUCER] ?? [],
            'arranger'  => $list[ARTIST_ARRANGER] ?? [],
        ];
    }

    /**
     * A cryptic representation of artists grouped by their roles, using magic
     * numbers that require consulting the source code to understand. Roles that
     * are not represent in the release group are not present in the result.
     *
     * @return array [1 => ["id" => 55, "name" => "Anne Other"], 7 => ["id" => 13, "name" => "Hugh Nose"]]
     */
    public function idList(): array {
        if (!isset($this->idList)) {
            $this->init();
        }
        return $this->idList;
    }

    /**
     * An even more cryptic representation of artists grouped by their roles,
     * that is a mix of magic numbers from idList(), but has all the entries of
     * roleList() with the aliasid key, however if a role is not present, the
     * role points to null. Used only for the artist ajax endpoint.
     *
     * Yes, having all these variants is insane.
     *
     * @return array [1 => ["id" => 55, "aliasid" => 48, "name" => "Anne Other"],
     *  2 => null, 3 => null, 4 => null, 5 => null, 6 => null,
     *  7 => ["id" => 13, "aliasid" => 13, "name" => "Hugh Nose"]]
     */
    public function legacyList(): array {
        if (!isset($this->roleList)) {
            $this->init();
        }
        $legacy = [];
        foreach ($this->roleList as $id => $list) {
            $offset = array_search($id, self::MAP);
            if ($offset !== false) {
                $legacy[$offset] = $list === [] ? null : $list;
            }
        }
        return $legacy;
    }

    public function matchName(array $matchList): array {
        if (!$matchList) {
            return [];
        }
        $matchList = array_flip(array_map('mb_strtolower', $matchList));
        $matched = [];
        $roleList = $this->roleList();
        foreach ($roleList as $artistList) {
            foreach ($artistList as $artist) {
                if (isset($matchList[mb_strtolower($artist['name'])])) {
                    $matched[] = $artist['name'];
                }
            }
        }
        return array_unique($matched);
    }

    public function modifyList(array $roleAliasList, int $role, \Gazelle\User $user): int {
        $aliasList = array_map(fn ($tuple) => $tuple[1], $roleAliasList);
        self::$db->prepared_query("
            UPDATE IGNORE torrents_artists SET
                artist_role_id = ?
            WHERE GroupID = ?
                AND AliasID IN (" . placeholders($aliasList) . ")
            ", $role, $this->object->id(), ...$aliasList
        );
        $affected = 0;
        foreach ($roleAliasList as [$oldRole, $aliasId]) {
            // Don't bother logging artists whose importance hasn't changed
            if ($oldRole === $role) {
                continue;
            }
            $artist = $this->manager->findByAliasId($aliasId);
            $change = "artist {$artist->id} ({$artist->name()}) changed role from "
                . ARTIST_TYPE[$oldRole] . " to " . ARTIST_TYPE[$role];
            $this->logger()
                ->group(
                    $this->object,
                    $user,
                    $change
                )
                ->general(
                    "$change in group {$this->object->id()} ({$this->object->title()}) by user {$user->label()}"
                );
            ++$affected;
        }
        $this->flush();
        return $affected;
    }

    public function removeList(array $roleAliasList, \Gazelle\User $user): int {
        $changed  = [];
        foreach ($roleAliasList as [$role, $aliasId]) {
            self::$db->prepared_query("
                DELETE FROM torrents_artists
                WHERE GroupID          = ?
                    AND AliasID        = ?
                    AND artist_role_id = ?
                ", $this->object->id(), $aliasId, (int)$role
            );
            if (self::$db->affected_rows()) {
                $artist = $this->manager->findByAliasId($aliasId);
                $changed[$artist->id] = $artist;
                $change = "artist {$artist->id} ({$artist->name()}) removed as " . ARTIST_TYPE[$role];
                $this->logger()
                    ->group(
                        $this->object,
                        $user,
                        $change
                    )
                    ->general(
                        "$change in group {$this->object->id()} ({$this->object->title()}) by user {$user->label()}"
                    );
            }
        }
        foreach ($changed as $artist) {
            if (!$artist->usageTotal()) {
                $artist->remove();
            }
        }
        $this->flush();
        return count($changed);
    }
}
