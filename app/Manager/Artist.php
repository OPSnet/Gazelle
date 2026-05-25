<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\BaseManager {
    protected const string ID_KEY = 'zz_a_%d';
    protected const string ROLE_KEY = 'artist_role';
    public const string ALIAS_KEY = 'alias_artist2_%d';

    protected array $role;
    protected int $groupId; // torrent or request context
    protected int $userId; // who is manipulating the torrents_artists or request_artist tables

    public function __construct() {
        $role = self::$cache->get_value(self::ROLE_KEY);
        if ($role === false) {
            self::$db->prepared_query("
                SELECT slug, artist_role_id, sequence, name, title, collection
                FROM artist_role
                ORDER BY artist_role_id
            ");
            $role = self::$db->to_array('slug', MYSQLI_ASSOC);
            self::$cache->cache_value(self::ROLE_KEY, $role, 86400 * 30);
        }
        $this->role = $role;
    }

    public function create(string $name): \Gazelle\Artist {
        $db = new \Gazelle\DB();
        self::$db->begin_transaction();
        $db->relaxConstraints(true);
        self::$db->prepared_query('
            INSERT INTO artists_group (PrimaryAlias) VALUES (0)
        ');
        $artistId = self::$db->inserted_id();

        self::$db->prepared_query('
            INSERT INTO artists_alias (ArtistID, Name)
            VALUES (?, ?)
            ', $artistId, $name
        );
        $aliasId = self::$db->inserted_id();
        self::$db->prepared_query('
            UPDATE artists_group SET PrimaryAlias = ? WHERE ArtistID = ?
            ', $aliasId, $artistId
        );
        $db->relaxConstraints(false);
        self::$db->commit();

        self::$cache->increment('stats_artist_count');

        return new \Gazelle\Artist($artistId);
    }

    public function findById(int $id): ?\Gazelle\Artist {
        $key = sprintf(self::ID_KEY, $id);
        $artistId = self::$cache->get_value($key);
        if ($artistId === false) {
            $artistId = self::$db->scalar("
                SELECT ArtistID FROM artists_group WHERE ArtistID = ?
                ", $id
            );
            if (!is_null($artistId)) {
                self::$cache->cache_value($key, $artistId, 7200);
            }
        }
        return $artistId ? new \Gazelle\Artist($artistId) : null;
    }

    public function findByIdAndRevision(int $id, int $revisionId): ?\Gazelle\Artist {
        $artistId = (int)self::$db->scalar("
            SELECT ag.ArtistID
            FROM artists_group ag
            INNER JOIN wiki_artists wa ON (wa.PageID = ag.ArtistID)
            WHERE wa.PageID = ?
                AND wa.RevisionID = ?
            ", $id, $revisionId
        );
        return $artistId ? new \Gazelle\Artist($artistId, null, $revisionId) : null;
    }

    public function findByName(string $name): ?\Gazelle\Artist {
        [$artistId, $aliasId] = self::$db->row("
            SELECT ArtistID,
                   CASE WHEN Redirect > 0
                        THEN Redirect
                        ELSE AliasID END AS AliasID
            FROM artists_alias WHERE Name = ?
            ", trim($name)
        );
        return $artistId ? new \Gazelle\Artist($artistId, $aliasId) : null;
    }

    public function findByNameAndRevision(string $name, int $revisionId): ?\Gazelle\Artist {
        $artistId = self::$db->scalar("
            SELECT ag.ArtistID
            FROM artists_group ag
            INNER JOIN artists_alias aa ON (ag.ArtistID = aa.ArtistID)
            WHERE aa.Name = ?
                AND ag.RevisionID = ?
            ", trim($name), $revisionId
        );
        return $artistId ? new \Gazelle\Artist((int)$artistId, null, $revisionId) : null;
    }

    public function findByAliasId(int $aliasId): ?\Gazelle\Artist {
        $key = sprintf(self::ALIAS_KEY, $aliasId);
        if ($artistId = self::$cache->get_value($key)) {
            return new \Gazelle\Artist($artistId, $aliasId);
        }
        [$artistId, $aliasId] = self::$db->row("
            SELECT ArtistID,
                   CASE WHEN Redirect > 0
                        THEN Redirect 
                        ELSE AliasID END AS AliasID
            FROM artists_alias WHERE AliasID = ?
            ", $aliasId
        );
        if ($artistId) {
            self::$cache->cache_value($key, $artistId);
            return new \Gazelle\Artist($artistId, $aliasId);
        }
        return null;
    }

    public function findRandom(): ?\Gazelle\Artist {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT r1.artist_id
                FROM artist_usage AS r1
                INNER JOIN artist_role AS ar USING (artist_role_id)
                CROSS JOIN (SELECT rand() * max(artist_id) AS artist_id FROM artist_usage) AS r2
                WHERE r1.artist_id >= r2.artist_id
                    AND ar.slug IN ('main', 'remixer', 'composer', 'conductor', 'dj', 'producer')
                GROUP BY r1.artist_id
                HAVING sum(r1.uses) >= ?
                LIMIT 1
                ", RANDOM_ARTIST_MIN_ENTRIES
            )
        );
    }

    public function autocompleteKey(string $prefix): string {
        return "artist_autocomp2_" . base64_encode(mb_strtolower(mb_substr($prefix, 0, 16)));
    }

    public function autocompletePrefixList(string $name): array {
        $name = trim(mb_strtolower($name));
        if ($name === '') {
            return [];
        }
        $limit = min(16, mb_strlen($name));
        $prefixList = [];
        for ($i = 1; $i <= $limit; ++$i) {
            $prefixList[] = mb_substr($name, 0, $i);
        }
        return $prefixList;
    }

    public function autocompleteKeyList(array $nameList): array {
        $keyList = [];
        foreach ($nameList as $name) {
            foreach ($this->autocompletePrefixList((string)$name) as $prefix) {
                $keyList[$this->autocompleteKey($prefix)] = true;
            }
        }
        return array_keys($keyList);
    }

    public function autocompleteList(string $prefix): array {
        $prefix = trim($prefix);
        if ($prefix == '') { return []; }
        $list = self::$cache->get($key = $this->autocompleteKey($prefix));
        if ($list === false) {
            $orderBy = 'm.exact_match DESC, sum(tls.Snatched) DESC, selected_alias.Name ASC';
            self::$db->prepared_query("
                SELECT m.ArtistID AS artist_id,
                    m.AliasID AS alias_id,
                    selected_alias.Name AS selected_name,
                    (selected_alias.Redirect > 0) AS selected_is_redirect
                FROM (
                    SELECT aa.ArtistID,
                        coalesce(min(CASE WHEN aa.Name = ? THEN aa.AliasID END), min(aa.AliasID)) AS AliasID,
                        max(aa.Name = ?) AS exact_match
                    FROM artists_alias aa
                    WHERE aa.Name LIKE concat(?, '%')
                    GROUP BY aa.ArtistID
                ) AS m
                INNER JOIN artists_alias        selected_alias ON (selected_alias.AliasID = m.AliasID)
                INNER JOIN artists_alias        a2  ON (a2.ArtistID = m.ArtistID)
                INNER JOIN torrents_artists     ta  ON (ta.AliasID = a2.AliasID)
                INNER JOIN torrents             t   ON (t.GroupID = ta.GroupID)
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                GROUP BY m.ArtistID, m.AliasID, m.exact_match, selected_alias.Name, selected_alias.Redirect
                ORDER BY $orderBy
                LIMIT 20
                ", $prefix, $prefix, str_replace("%", "\\%", $prefix),
            );
            $list = array_values(array_filter(array_map(function ($row): ?array {
                $artist = $this->findByAliasId((int)$row['alias_id']);
                if (is_null($artist)) {
                    return null;
                }
                // Preserve the matched alias for the top-line label; manager lookup may canonicalize redirects.
                $displayName = $row['selected_name'];
                return [
                    'data'   => $artist->id,
                    'value'  => $displayName,
                    'widget' => $artist->widgetContext([
                        'display_name' => $displayName,
                        'show_aka'     => (bool)$row['selected_is_redirect'] && $artist->primaryName() !== $displayName,
                    ]),
                ];
            }, self::$db->to_array(false, MYSQLI_ASSOC))));
            self::$cache->cache_value($key, $list, 3600);
        }
        return $list;
    }

    public function aliasUseTotal(int $aliasId): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM artists_alias AS aa
            INNER JOIN artists_alias AS aa2 USING (ArtistID)
            WHERE aa.AliasID = ?
            ", $aliasId
        );
    }

    public function roleExists(int $categoryId, int $roleId): bool {
        // TODO: roles by category are not yet a thing so ignore categoryId for now
        $categoryId = 0;
        return (bool)self::$db->scalar("
            SELECT 1 FROM artist_role WHERE artist_role_id = ?
            ", $roleId
        );
    }

    public function tgroupList(int $aliasId, \Gazelle\Manager\TGroup $tgMan): array {
        self::$db->prepared_query("
            SELECT GroupID FROM torrents_artists WHERE AliasID = ?
            ", $aliasId
        );
        return array_filter(
            array_map(
                fn ($id) => $tgMan->findById($id),
                self::$db->collect(0)
            ),
            fn ($tgroup) => !empty($tgroup)
        );
    }

    public function setGroupId(int $groupId): static {
        $this->groupId = $groupId;
        return $this;
    }

    public function setUserId(int $userId): static {
        $this->userId = $userId;
        return $this;
    }

    public function sectionName(int $sectionId): ?string {
        return new \Gazelle\ReleaseType()->findExtendedNameById($sectionId);
    }

    public function sectionLabel(int $sectionId): string {
        return strtolower(str_replace(' ', '_', $this->sectionName($sectionId)));
    }

    public function sectionTitle(int $sectionId): string {
        return new \Gazelle\ReleaseType()->sectionTitle($sectionId);
    }
}
