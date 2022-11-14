<?php

namespace Gazelle\ArtistRole;

class TGroup extends \Gazelle\ArtistRole {
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

    protected function artistListQuery(): \mysqli_result {
        return self::$db->prepared_query("
            SELECT ta.Importance,
                ta.ArtistID,
                aa.Name,
                ta.AliasID
            FROM torrents_artists AS ta
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ta.GroupID = ?
            ORDER BY ta.GroupID, ta.Importance ASC, aa.Name ASC
            ", $this->id
        );
    }

    protected function init() {
        $this->artistList = $this->artistList();
        $this->roleList = array_fill_keys(array_values(self::MAP), []);
        $this->idList = [];
        while ([$role, $artistId, $artistName, $aliasId] = self::$db->next_record(MYSQLI_NUM, false)) {
            $this->idList[$role][] = [
                'id'      => $artistId,
                'name'    => $artistName,
            ];
            $this->roleList[self::MAP[$role]][] = [
                'id'      => $artistId,
                'aliasid' => $aliasId,
                'name'    => $artistName,
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
        if (!isset($this->idList)) {
            $this->init();
        }
        $list = $this->idList;
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
            $legacy[array_search($id, self::MAP)] = empty($list) ? null : $list;
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
}
