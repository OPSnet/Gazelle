<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_a_%d';
    protected const ROLE_KEY = 'artist_role';

    protected array $role;
    protected int $groupId; // torrent or request context
    protected int $userId; // who is manipulating the torrents_artists or requests_artists tables

    public function __construct() {
        $role = self::$cache->get_value(self::ROLE_KEY);
        if ($role === false) {
            self::$db->prepared_query("
                SELECT slug, artist_role_id, sequence, name, title, collection
                FROM artist_role
                ORDER BY artist_role_id
            ");
            $role = self::$db->to_array('slug', MYSQLI_ASSOC, false);
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

    public function findById(int $artistId): ?\Gazelle\Artist {
        $key = sprintf(self::ID_KEY, $artistId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ArtistID FROM artists_group WHERE ArtistID = ?
                ", $artistId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Artist($id) : null;
    }

    public function findByIdAndRevision(int $artistId, int $revisionId): ?\Gazelle\Artist {
        $id = (int)self::$db->scalar("
            SELECT ag.ArtistID
            FROM artists_group ag
            INNER JOIN wiki_artists wa ON (wa.PageID = ag.ArtistID)
            WHERE wa.PageID = ?
                AND wa.RevisionID = ?
            ", $artistId, $revisionId
        );
        return $id ? new \Gazelle\Artist($id, null, $revisionId) : null;
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
        [$artistId, $aliasId] = self::$db->row("
            SELECT ArtistID,
                   CASE WHEN Redirect > 0
                        THEN Redirect 
                        ELSE AliasID END AS AliasID
            FROM artists_alias WHERE AliasID = ?
            ", $aliasId
        );
        return $artistId ? new \Gazelle\Artist($artistId, $aliasId) : null;
    }

    public function findRandom(): ?\Gazelle\Artist {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT r1.artist_id
                FROM artist_usage r1,
                (SELECT rand() * max(artist_id) AS artist_id FROM artist_usage) AS r2
                WHERE r1.artist_id >= r2.artist_id
                    AND r1.role in ('1', '3', '4', '5', '6', '7')
                GROUP BY r1.artist_id
                HAVING sum(r1.uses) >= ?
                LIMIT 1
                ", RANDOM_ARTIST_MIN_ENTRIES
            )
        );
    }

    public function autocompleteKey(string $prefix): string {
        return "artist_autocomp_" . base64_encode(mb_strtolower(mb_substr($prefix, 0, 16)));
    }

    public function autocompleteList(string $prefix): array {
        $prefix = trim($prefix);
        if ($prefix == '') {
            return [];
        }
        $key = $this->autocompleteKey($prefix);
        $list = self::$cache->get($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT a.ArtistID AS data,
                    aa.Name       AS value
                FROM artists_group AS a
                INNER JOIN artists_alias        aa  ON (a.PrimaryAlias = aa.AliasID)
                INNER JOIN torrents_artists     ta  ON (ta.AliasID = aa.AliasID)
                INNER JOIN torrents             t   ON (t.GroupID = ta.GroupID)
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                WHERE aa.Name LIKE concat(?, '%')
                GROUP BY a.ArtistID, aa.Name
                ORDER BY sum(tls.Snatched) DESC
                LIMIT 20",
                str_replace("%", "\\%", $prefix)
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
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

    public function tgroupList(int $aliasId, \Gazelle\Manager\TGroup $tgMan): array {
        self::$db->prepared_query("
            SELECT GroupID FROM torrents_artists WHERE AliasID = ?
            ", $aliasId
        );
        return array_filter(
            array_map(
                fn($id) => $tgMan->findById($id),
                self::$db->collect(0, false)
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
        return (new \Gazelle\ReleaseType())->findExtendedNameById($sectionId);
    }

    public function sectionLabel(int $sectionId): string {
        return strtolower(str_replace(' ', '_', $this->sectionName($sectionId)));
    }

    public function sectionTitle(int $sectionId): string {
        return (new \Gazelle\ReleaseType())->sectionTitle($sectionId);
    }
}
