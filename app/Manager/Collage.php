<?php

namespace Gazelle\Manager;

class Collage extends \Gazelle\BaseManager {

    protected const CACHE_DEFAULT_ARTIST = 'collage_default_artist_%d';
    protected const CACHE_DEFAULT_GROUP  = 'collage_default_group_%d';
    protected const TGROUP_GENERAL_KEY   = 'torrent_collages_%d';
    protected const TGROUP_PERSONAL_KEY  = 'torrent_collages_personal_%d';
    protected const ARTIST_KEY           = 'artists_collages_%d';
    protected const ID_KEY = 'zz_c_%d';

    protected \Gazelle\Util\ImageProxy $imageProxy;

    public function create(\Gazelle\User $user, int $categoryId, string $name, string $description, string $tagList, \Gazelle\Log $logger) {
        self::$db->prepared_query("
            INSERT INTO collages
                   (UserID, CategoryID, Name, Description, TagList)
            VALUES (?,      ?,          ?,    ?,           ?)
            ", $user->id(), $categoryId, trim($name), trim($description), trim($tagList)
        );
        $id = self::$db->inserted_id();
        (new \Gazelle\Stats\User($user->id()))->increment('collage_total');
        $logger->general("Collage $id ($name) was created by " . $user->username());
        return new \Gazelle\Collage($id);
    }

    public function findById(int $collageId): ?\Gazelle\Collage {
        $key = sprintf(self::ID_KEY, $collageId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM collages WHERE ID = ?
                ", $collageId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Collage($id) : null;
    }

    public function findByName(string $name): ?\Gazelle\Collage {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM collages WHERE Name = ?
            ", $name
        ));
    }

    public function findPersonalByUserId(int $userId): array {
        self::$db->prepared_query("
            SELECT ID
            FROM collages
            WHERE UserID = ?
                AND CategoryID = 0
                AND Deleted = '0'
            ORDER BY Featured DESC, Name ASC
            ", $userId
        );
        return array_map(fn ($id) => $this->findById($id), self::$db->collect(0, false));
    }

    public function recoverById(int $id) {
        $collageId = self::$db->scalar("SELECT ID FROM collages WHERE ID = ?", $id);
        if ($collageId !== null) {
            return $this->recover($collageId);
        }
    }

    public function recoverByName(string $name) {
        $collageId = self::$db->scalar("SELECT ID FROM collages WHERE Name = ?", $name);
        if ($collageId !== null) {
            return $this->recover($collageId);
        }
    }

    protected function recover(int $id) {
        self::$db->prepared_query("
            UPDATE collages SET
                Deleted = '0'
            WHERE ID = ?
            ", $id
        );
        return new \Gazelle\Collage($id);
    }

    public function setImageProxy(\Gazelle\Util\ImageProxy $imageProxy) {
        $this->imageProxy = $imageProxy;
        return $this;
    }

    public function tgroupCover(\Gazelle\TGroup $tgroup): string {
        return self::$twig->render('collage/row.twig', [
            'group_id'   => $tgroup->id(),
            'image'      => isset($this->imageProxy) ? $this->imageProxy->process($tgroup->image()) : $tgroup->image(),
            'name'       => $tgroup->displayNameText(),
            'tags'       => implode(', ', array_map(fn($n) => "#{$n}", $tgroup->tagNameList())),
            'tags_plain' => implode(', ', $tgroup->tagNameList()),
        ]);
    }

    public function coverRow(\Gazelle\TGroup $tgroup): string {
        return self::$twig->render('collage/row.twig', [
            'group_id'   => $tgroup->id(),
            'image'      => isset($this->imageProxy) ? $this->imageProxy->process($tgroup->image()) : $tgroup->image(),
            'name'       => $tgroup->displayNameText(),
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

    protected function idsToNames(array $idList): array {
        if (empty($idList)) {
            return [];
        }
        self::$db->prepared_query("
            SELECT c.ID AS id,
                c.Name AS name
            FROM collages c
            WHERE c.ID IN (" . placeholders($idList) . ")
            ORDER BY c.Updated DESC
            ", ...$idList
        );
        return self::$db->to_pair('id', 'name', false);
    }

    public function addToArtistCollageDefault(int $userId, int $artistId): array {
        $key = sprintf(self::CACHE_DEFAULT_ARTIST, $userId);
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
                ", $userId, COLLAGE_ARTISTS_ID, $artistId
            );
            $list = self::$db->collect(0, false);
            if (empty($list)) {
                // Prevent empty IN operator: WHERE ID IN ()
                $list = [0];
            }

            // Ensure that some of the other collages the user has worked on are present
            self::$db->prepared_query("
                SELECT c.ID
                FROM collages c
                INNER JOIN collages_artists ca ON (ca.CollageID = c.ID AND ca.UserID = ?)
                WHERE c.Locked = '0'
                    AND c.Deleted = '0'
                    AND c.UserID != ?
                    AND c.CategoryID = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM collages_artists WHERE CollageID = c.ID AND ArtistID = ?
                    )
                GROUP BY c.ID
                ORDER BY max(ca.AddedOn) DESC
                LIMIT 5
                ", $userId, $userId, COLLAGE_ARTISTS_ID, $artistId
            );
            $default = $this->idsToNames(array_merge($list, self::$db->collect(0)));
            self::$cache->cache_value($key, $default, 86400);
        }
        return $default;
    }

    public function addToCollageDefault(int $userId, int $groupId): array {
        $key = sprintf(self::CACHE_DEFAULT_GROUP, $userId);
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
                ", $userId, COLLAGE_PERSONAL_ID, $groupId
            );
            $list = self::$db->collect(0, false) ?: [0];

            // Ensure that some (theirs and by others) of the other collages the user has worked on are present
            self::$db->prepared_query("
                SELECT c.ID
                FROM collages c
                INNER JOIN collages_torrents ca ON (ca.CollageID = c.ID AND ca.UserID = ?)
                WHERE c.Locked = '0'
                    AND c.Deleted = '0'
                    AND c.CategoryID != ?
                    AND NOT EXISTS (
                        SELECT 1 FROM collages_torrents WHERE CollageID = c.ID AND GroupID = ?
                    )
                GROUP BY c.ID
                ORDER BY max(ca.AddedOn) DESC
                LIMIT 5
                ", $userId, COLLAGE_PERSONAL_ID, $groupId
            );
            unset($list[0]);
            $default = $this->idsToNames(array_merge($list, self::$db->collect(0)));
            self::$cache->cache_value($key, $default, 86400);
        }
        return $default;
    }

    public function flushDefaultArtist(int $userId) {
        self::$cache->delete_value(sprintf(self::CACHE_DEFAULT_ARTIST, $userId));
    }

    public function flushDefaultGroup(int $userId) {
        self::$cache->delete_value(sprintf(self::CACHE_DEFAULT_GROUP, $userId));
    }

    public function autocomplete(string $text): array {
        $maxLength = 10;
        $length = min($maxLength, max(1, mb_strlen($text)));
        if ($length < 3) {
            return [];
        }
        $stem = mb_strtolower(mb_substr($text, 0, $length));
        $key = 'autocomplete_collage_' . $length . '_' . $stem;
        if (($autocomplete = self::$cache->get($key)) === false) {
            self::$db->prepared_query("
                SELECT ID,
                    Name
                FROM collages
                WHERE Locked = '0'
                    AND Deleted = '0'
                    AND CategoryID NOT IN (?, ?)
                    AND lower(Name) LIKE concat('%', ?, '%')
                ORDER BY NumTorrents DESC, Name
                LIMIT 10
                ", COLLAGE_ARTISTS_ID, COLLAGE_PERSONAL_ID, $stem
            );
            $pairs = self::$db->to_pair('ID', 'Name', false);
            $autocomplete = [];
            foreach($pairs as $key => $value) {
                $autocomplete[] = ['data' => $key, 'value' => $value];
            }
            self::$cache->cache_value($key, $autocomplete, 1800 + 7200 * ($maxLength - $length));
        }
        return $autocomplete;
    }

    public function subscribedGroupCollageList(int $userId, bool $showRecent): array {
        $cond = ['s.UserID = ?'];
        $args = [$userId];
        if ($showRecent) {
            $cond[] = 'ct.AddedOn > s.LastVisit';
            $groupIds = 'group_concat(ct.GroupID ORDER BY ct.AddedOn)';
        } else {
            $groupIds = 'group_concat(if(ct.AddedOn > s.LastVisit, ct.GroupID, NULL) ORDER BY ct.AddedOn)';
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

    public function subscribedArtistCollageList(int $userId, bool $showRecent): array {
        $cond = ['s.UserID = ?'];
        $args = [$userId];
        if ($showRecent) {
            $cond[] = 'ca.AddedOn > s.LastVisit';
            $artistIds = 'group_concat(ca.ArtistID ORDER BY ca.AddedOn)';
        } else {
            $artistIds = 'group_concat(if(ca.AddedOn > s.LastVisit, ca.ArtistID, NULL) ORDER BY ca.AddedOn)';
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
    public function tgroupGeneralSummary(int $tgroupId): array {
        $key = sprintf(self::TGROUP_GENERAL_KEY, $tgroupId);
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
                ", COLLAGE_PERSONAL_ID, $tgroupId
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
    public function tgroupPersonalSummary(int $tgroupId): array {
        $key = sprintf(self::TGROUP_PERSONAL_KEY, $tgroupId);
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
                ", COLLAGE_PERSONAL_ID, $tgroupId
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
    public function artistSummary(int $artistId): array {
        $key = sprintf(self::ARTIST_KEY, $artistId);
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
                ", COLLAGE_ARTISTS_ID, $artistId
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
