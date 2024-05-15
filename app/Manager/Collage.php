<?php

namespace Gazelle\Manager;

use Gazelle\Enum\CollageType;

class Collage extends \Gazelle\BaseManager {
    final public const ID_KEY = 'zz_cg_%d';
    final protected const CACHE_DEFAULT_ARTIST = 'collage_def_artist_%d';
    final protected const CACHE_DEFAULT_GROUP  = 'collage_def_tgroup_%d';
    final protected const TGROUP_GENERAL_KEY   = 'torrent_collages_%d';
    final protected const TGROUP_PERSONAL_KEY  = 'torrent_collages_personal_%d';
    final protected const ARTIST_KEY           = 'artists_collages_%d';

    protected \Gazelle\Util\ImageProxy $imageProxy;

    public static function findType(int $type): ?\Gazelle\Enum\CollageType {
        return match ($type) {
            0 => CollageType::personal,
            1 => CollageType::theme,
            2 => CollageType::genre,
            3 => CollageType::discography,
            4 => CollageType::label,
            5 => CollageType::staffPick,
            6 => CollageType::chart,
            7 => CollageType::artist,
            8 => CollageType::award,
            9 => CollageType::series,
            default => null,
        };
    }

    public function create(
        \Gazelle\User $user,
        int $categoryId,
        string $name,
        string $description,
        string $tagList,
        \Gazelle\Log $logger
    ): \Gazelle\Collage {
        self::$db->prepared_query("
            INSERT INTO collages
                   (UserID, CategoryID, Name, Description, TagList)
            VALUES (?,      ?,          ?,    ?,           ?)
            ", $user->id(), $categoryId, trim($name), trim($description), trim($tagList)
        );
        $id = self::$db->inserted_id();
        $user->stats()->increment('collage_total');
        (new \Gazelle\Stats\Collage())->increment();
        $logger->general("Collage $id ($name) was created by {$user->username()}");
        return new \Gazelle\Collage($id, $categoryId);
    }

    public function findById(int $collageId): ?\Gazelle\Collage {
        $key = sprintf(self::ID_KEY, $collageId);
        $idCategory = self::$cache->get_value($key);
        if ($idCategory === false) {
            $idCategory = self::$db->row("
                SELECT ID, CategoryID FROM collages WHERE ID = ?
                ", $collageId
            );
            if ($idCategory) {
                self::$cache->cache_value($key, $idCategory, 7200);
            }
        }
        return $idCategory ? new \Gazelle\Collage(...$idCategory) : null;
    }

    public function findByName(string $name): ?\Gazelle\Collage {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM collages WHERE Name = ?
            ", $name
        ));
    }

    public function findRandom(): ?\Gazelle\Collage {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT r1.ID
                FROM collages AS r1,
                    (
                        SELECT rand() * max(ID) AS ID
                        FROM collages
                        WHERE Deleted = '0'
                            AND NumTorrents >= ?
                    ) AS r2
                WHERE r1.ID >= r2.ID
                    AND r1.Deleted = '0'
                    AND NumTorrents >= ?
                LIMIT 1
                ", RANDOM_COLLAGE_MIN_ENTRIES, RANDOM_COLLAGE_MIN_ENTRIES
            )
        );
    }

    public function findPersonalByUser(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT ID
            FROM collages
            WHERE UserID = ?
                AND CategoryID = 0
                AND Deleted = '0'
            ORDER BY Featured DESC, Name ASC
            ", $user->id()
        );
        return array_map(fn($id) => $this->findById($id), self::$db->collect(0, false));
    }

    public function recoverById(int $id): ?\Gazelle\Collage {
        return $this->recover((int)self::$db->scalar("SELECT ID FROM collages WHERE ID = ?", $id));
    }

    public function recoverByName(string $name): ?\Gazelle\Collage {
        return $this->recover((int)self::$db->scalar("SELECT ID FROM collages WHERE Name = ?", $name));
    }

    protected function recover(int $id): ?\Gazelle\Collage {
        if ($id) {
            self::$db->prepared_query("
                UPDATE collages SET
                    Deleted = '0'
                WHERE ID = ?
                ", $id
            );
        }
        return $this->findById($id);
    }

    public function setImageProxy(\Gazelle\Util\ImageProxy $imageProxy): \Gazelle\Manager\Collage {
        $this->imageProxy = $imageProxy;
        return $this;
    }

    public function tgroupCover(\Gazelle\TGroup $tgroup): string {
        return self::$twig->render('collage/row.twig', [
            'group_id'   => $tgroup->id(),
            'image'      => image_cache_encode($tgroup->image(), height: 150, width: 150),
            'name'       => $tgroup->text(),
            'tags'       => implode(', ', array_map(fn($n) => "#{$n}", $tgroup->tagNameList())),
            'tags_plain' => implode(', ', $tgroup->tagNameList()),
        ]);
    }

    /**
     * Create a generic collage name for a personal collage.
     * Used for people who lack the privileges create personal collages with arbitrary names
     *
     * @return string name of the collage
     */
    public function personalCollageName(string $name): string {
        $new = $name . "'s personal collage";
        self::$db->prepared_query('
            SELECT ID FROM collages WHERE Name = ?
            ', $new
        );
        $i = 1;
        $basename = $new;
        while (self::$db->has_results()) {
            $new = "$basename no. " . ++$i;
            self::$db->prepared_query('
                SELECT ID FROM collages WHERE Name = ?
                ', $new
            );
        }
        return $new;
    }

    public function addToArtistCollageDefault(int $artistId, \Gazelle\User $user): array {
        $userId  = $user->id();
        $key     = sprintf(self::CACHE_DEFAULT_ARTIST, $userId);
        $default = self::$cache->get_value($key);
        if ($default === false) {
            // Ensure that some of the creator's collages are in the result
            self::$db->prepared_query("
                SELECT c.ID
                FROM collages c
                WHERE c.Locked = '0'
                    AND c.Deleted = '0'
                    AND c.UserID = ?
                    AND c.CategoryID = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM collages_artists WHERE CollageID = c.ID AND ArtistID = ?
                    )
                ORDER BY c.Updated DESC
                LIMIT 5
                ", $userId, CollageType::artist->value, $artistId
            );
            $default = self::$db->collect(0, false);

            // Ensure that some of the other collages the user has worked on are present
            self::$db->prepared_query("
                SELECT c.ID
                FROM collages c
                INNER JOIN collages_artists ca ON (ca.CollageID = c.ID AND ca.UserID = ?)
                WHERE c.Locked = '0'
                    AND c.Deleted = '0'
                    AND c.UserID != ?
                    AND c.CategoryID = ?
                    AND (
                        c.MaxGroupsPerUser = 0
                        OR (c.MaxGroupsPerUser < (
                            SELECT count(*) FROM collages_torrents ct WHERE CollageID = c.ID AND ct.UserID = ?
                        ))
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM collages_artists WHERE CollageID = c.ID AND ArtistID = ?
                    )
                GROUP BY c.ID
                ORDER BY max(ca.AddedOn) DESC
                LIMIT 5
                ", $userId, $userId, CollageType::artist->value, $userId, $artistId
            );
            $default = array_merge($default, self::$db->collect(0, false));
            self::$cache->cache_value($key, $default, 86400);
        }
        $list = [];
        foreach ($default as $id) {
            $collage = $this->findById($id);
            if ($collage) {
                $list[] = $collage;
            }
        }
        return $list;
    }

    public function addToCollageDefault(int $groupId, \Gazelle\User $user): array {
        $key = sprintf(self::CACHE_DEFAULT_GROUP, $user->id());
        $default = self::$cache->get_value($key);
        if ($default === false) {
            // All of their personal collages are in the result
            self::$db->prepared_query("
                SELECT c.ID
                FROM collages c
                WHERE c.Locked = '0'
                    AND c.Deleted = '0'
                    AND c.UserID = ?
                    AND c.CategoryID = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM collages_torrents WHERE CollageID = c.ID AND GroupID = ?
                    )
                ORDER BY c.Updated DESC
                ", $user->id(), CollageType::personal->value, $groupId
            );
            $default = self::$db->collect(0, false);

            // Ensure that some (theirs and by others) of the other collages the user has worked on are present
            self::$db->prepared_query("
                SELECT c.ID
                FROM collages c
                INNER JOIN collages_torrents ca ON (ca.CollageID = c.ID AND ca.UserID = ?)
                WHERE c.Locked = '0'
                    AND c.Deleted = '0'
                    AND c.CategoryID != ?
                    AND (
                        c.MaxGroupsPerUser = 0
                        OR (c.MaxGroupsPerUser < (
                            SELECT count(*) FROM collages_torrents ct WHERE CollageID = c.ID AND ct.UserID = ?
                        ))
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM collages_torrents WHERE CollageID = c.ID AND GroupID = ?
                    )
                GROUP BY c.ID
                ORDER BY max(ca.AddedOn) DESC
                LIMIT 5
                ", $user->id(), CollageType::personal->value, $user->id(), $groupId
            );
            $default = array_merge($default, self::$db->collect(0, false));
            self::$cache->cache_value($key, $default, 86400);
        }
        $list = [];
        foreach ($default as $id) {
            $collage = $this->findById($id);
            if ($collage) {
                $list[] = $collage;
            }
        }
        return $list;
    }

    public function flushDefaultArtist(\Gazelle\User $user): static {
        self::$cache->delete_value(sprintf(self::CACHE_DEFAULT_ARTIST, $user->id()));
        return $this;
    }

    public function flushDefaultGroup(\Gazelle\User $user): static {
        self::$cache->delete_value(sprintf(self::CACHE_DEFAULT_GROUP, $user->id()));
        return $this;
    }

    public function autocomplete(string $text, bool $isArtist = false): array {
        $maxLength = 16;
        $length = min($maxLength, max(1, mb_strlen($text)));
        if ($length < 3) {
            return [];
        }
        $stem = mb_strtolower(mb_substr($text, 0, $length));
        $key = 'autocomp_collage_' . ($isArtist ? 'a_' : '') . "_$stem";
        $autocomplete = self::$cache->get($key);
        if ($autocomplete === false) {
            if ($isArtist) {
                self::$db->prepared_query("
                    SELECT ID,
                        Name
                    FROM collages
                    WHERE Locked = '0'
                        AND Deleted = '0'
                        AND CategoryID = ?
                        AND Name LIKE concat('%', ?, '%')
                    ORDER BY NumTorrents DESC, Name
                    LIMIT 10
                    ", CollageType::artist->value, $stem
                );
            } else {
                self::$db->prepared_query("
                    SELECT ID,
                        Name
                    FROM collages
                    WHERE Locked = '0'
                        AND Deleted = '0'
                        AND CategoryID NOT IN (?, ?)
                        AND Name LIKE concat('%', ?, '%')
                    ORDER BY NumTorrents DESC, Name
                    LIMIT 10
                    ", CollageType::artist->value, CollageType::personal->value, $stem
                );
            }
            $pairs = self::$db->to_pair('ID', 'Name', false);
            $autocomplete = [];
            foreach ($pairs as $key => $value) {
                $autocomplete[] = ['data' => $key, 'value' => $value];
            }
            self::$cache->cache_value($key, $autocomplete, 1800 + 7200 * ($maxLength - $length));
        }
        return $autocomplete;
    }

    public function subscribedTGroupCollageList(\Gazelle\User $user, bool $viewAll): array {
        $cond = ['s.UserID = ?'];
        $args = [$user->id()];
        if ($viewAll) {
            $groupIds = 'min(ct.GroupID)';
        } else {
            $cond[] = 'ct.AddedOn > s.LastVisit';
            $groupIds = 'group_concat(ct.GroupID ORDER BY ct.AddedOn)';
        }
        self::$db->prepared_query("
            SELECT c.ID       AS collageId,
                c.Name        AS name,
                c.NumTorrents AS nrEntries,
                s.LastVisit   AS lastVisit,
                $groupIds     AS groupIds
            FROM collages AS c
            INNER JOIN users_collage_subs AS s ON (s.CollageID = c.ID)
            INNER JOIN collages_torrents AS ct ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0'
                AND " . implode(' AND ', $cond) . "
            GROUP BY c.ID
            ", ...$args
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$entry) {
            $entry['groupIds'] = is_null($entry['groupIds']) ? [] : explode(',', $entry['groupIds']);
        }
        return $list;
    }

    public function subscribedArtistCollageList(\Gazelle\User $user, bool $viewAll): array {
        $cond = ['s.UserID = ?'];
        $args = [$user->id()];
        if ($viewAll) {
            $artistIds = 'min(ca.ArtistID)';
        } else {
            $cond[] = 'ca.AddedOn > s.LastVisit';
            $artistIds = 'group_concat(ca.ArtistID ORDER BY ca.AddedOn)';
        }
        self::$db->prepared_query("
            SELECT c.ID       AS collageId,
                c.Name        AS name,
                c.NumTorrents AS nrEntries,
                s.LastVisit   AS lastVisit,
                $artistIds    AS artistIds
            FROM collages AS c
            INNER JOIN users_collage_subs AS s ON (s.CollageID = c.ID)
            INNER JOIN collages_artists AS ca ON (ca.CollageID = c.ID)
            WHERE c.Deleted = '0'
                AND " . implode(' AND ', $cond) . "
            GROUP BY c.ID
            ", ...$args
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$entry) {
            $entry['artistIds'] = is_null($entry['artistIds']) ? [] : explode(',', $entry['artistIds']);
        }
        return $list;
    }

    /**
     * In how many general (non-personal) collages does a given torrent group
     * appear? The result is separated into two groups, those that will be
     * displayed "above the fold" and those that are hidden "below the fold".
     * The total (which has to be calculated here anyway), is also returned to
     * the caller, so that the code does not have to check whether the arrays are
     * empty (to skip the display entirely).
     *
     * The contents of an entry is ['id', 'name', 'total']
     *
     * @return array [total results, array above, array below]
     */
    public function tgroupGeneralSummary(\Gazelle\TGroup $tgroup): array {
        $key = sprintf(self::TGROUP_GENERAL_KEY, $tgroup->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT c.ID       AS id,
                    c.Name        AS name,
                    c.NumTorrents AS total
                FROM collages AS c
                INNER JOIN collages_torrents AS ct ON (ct.CollageID = c.ID)
                WHERE Deleted = '0'
                    AND CategoryID != ?
                    AND ct.GroupID = ?
                ORDER BY c.updated DESC
                ", CollageType::personal->value, $tgroup->id()
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $list, 3600 * 6);
        }
        return $this->listShuffle(COLLAGE_SAMPLE_THRESHOLD, $list);
    }

    /**
     * In how many general (non-personal) collages does a given
     * torrent group appear?
     *
     * @see \Gazelle\Manager\Collage::tgroupGeneralSummary()
     * @return array [total results, array above, array below]
     */
    public function tgroupPersonalSummary(\Gazelle\TGroup $tgroup): array {
        $key = sprintf(self::TGROUP_PERSONAL_KEY, $tgroup->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT c.ID       AS id,
                    c.Name        AS name,
                    c.NumTorrents AS total
                FROM collages AS c
                INNER JOIN collages_torrents AS ct ON (ct.CollageID = c.ID)
                WHERE Deleted = '0'
                    AND CategoryID = ?
                    AND ct.GroupID = ?
                ORDER BY c.updated DESC
                ", CollageType::personal->value, $tgroup->id()
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $list, 3600 * 6);
        }
        return $this->listShuffle(PERSONAL_COLLAGE_SAMPLE_THRESHOLD, $list);
    }

    /**
     * In how many collages does a given artist appear?
     *
     * @see \Gazelle\Manager\Collage::tgroupGeneralSummary()
     * @return array [total results, array above, array below]
     */
    public function artistSummary(\Gazelle\Artist $artist): array {
        $key = sprintf(self::ARTIST_KEY, $artist->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT c.ID       AS id,
                    c.Name        AS name,
                    c.NumTorrents AS total
                FROM collages AS c
                INNER JOIN collages_artists AS ca ON (ca.CollageID = c.ID)
                WHERE Deleted = '0'
                    AND CategoryID = ?
                    AND ca.ArtistID = ?
                ORDER BY c.updated DESC
                ", CollageType::artist->value, $artist->id()
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $list, 3600 * 6);
        }
        return $this->listShuffle(COLLAGE_SAMPLE_THRESHOLD, $list);
    }

    /**
     * Take an array and if there are more entries than a threshold,
     * then split it into two arrays, the first containing the threshold
     * number of random entries and the overflow contained in the second.
     *
     * If there are insufficient entries to trigger an overflow, all
     * the entries will be in the first array and the second will be
     * empty.
     *
     * @return array [total results, array above, array below]
     */
    public function listShuffle(int $threshold, array $list): array {
        $total = count($list);
        if ($total <= $threshold) {
            return ['total' => $total, 'above' => $list, 'below' => []];
        }

        $choose = range(0, $total - 1);
        shuffle($choose);
        $choose = array_slice($choose, 0, $threshold);

        $above = [];
        $below = [];
        foreach ($list as $idx => $row) {
            if (in_array($idx, $choose)) {
                $above[] = $row;
            } else {
                $below[] = $row;
            }
        }
        return ['total' => $total, 'above' => $above, 'below' => $below];
    }
}
