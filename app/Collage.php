<?php

namespace Gazelle;

class Collage extends Base {
    protected $id;
    protected $items;
    protected $ownerId;
    protected $categoryId;
    protected $deleted;
    protected $description;
    protected $featured;
    protected $locked;
    protected $name;
    protected $numEntries;
    protected $maxGroups;
    protected $maxGroupsPerUser;
    protected $sortNewest;
    protected $numSubscribers;
    protected $tags; // these are added at creation
    protected $torrentTags; // these are derived from the torrents added to the collage
    protected $updated;
    protected $userId;
    protected $userSubscriptions;

    /* these are only loaded on a torrent collage display */
    protected $torrents;
    protected $groupIds;

    /* these are only loaded on any collage display */
    protected $lockedForUser;
    protected $artists;
    protected $contributors;

    const CACHE_KEY    = 'collagev2_%d';
    const DISPLAY_KEY  = 'collagev2_display_%d';
    const SUBS_KEY     = 'collage_subs_user_%d';
    const SUBS_NEW_KEY = 'collage_subs_user_new_%d';

    /**
     * Collage constructor.
     * @param int $id collage id
     */
    public function __construct(int $id) {
        parent::__construct();

        $this->id = $id;
        $this->artists = [];
        $this->contributors = [];

        $key = sprintf(self::CACHE_KEY, $id);
        $info = $this->cache->get_value($key);
        if ($info === false) {
            $this->db->prepared_query("
                SELECT c.Deleted, c.TagList,
                    c.UserID, c.CategoryID, c.Updated, c.Subscribers, c.NumTorrents,
                    c.MaxGroups, c.MaxGroupsPerUser, c.Locked, c.Name, c.Description, c.Featured,
                    CASE WHEN cha.CollageID IS NULL THEN 0 ELSE 1 END as SortNewest
                FROM collages c
                LEFT JOIN collage_has_attr cha ON (cha.CollageID = c.ID)
                LEFT JOIN collage_attr ca ON (ca.ID = cha.CollageAttrID and ca.Name = ?)
                WHERE c.ID = ?
                ", 'sort-newest', $id
            );
            if ($this->db->has_results()) {
                $info = $this->db->next_record(MYSQLI_NUM, false);
            }
            else {
                /* Need some sensible defaults for some fields if the collage doesn't exist in the DB. */
                $info = [true, ''];
            }
            $this->cache->cache_value($key, $info, 7200);
            $this->cache->delete_value(sprintf('collage_%d', $this->id));
        }
        [
            $this->deleted, $taglist,
            $this->ownerId, $this->categoryId, $this->updated, $this->numSubscribers, $this->numEntries,
            $this->maxGroups, $this->maxGroupsPerUser, $this->locked, $this->name, $this->description, $this->featured,
            $this->sortNewest
        ] = $info;
        $this->tags = explode(' ', $taglist);
        if ($this->categoryId == COLLAGE_ARTISTS_ID) {
            $this->loadArtists();
        } else {
            $this->loadTorrents();
        }
    }

    protected function loadArtists() {
        $this->db->prepared_query("
            SELECT
                ca.ArtistID,
                ag.Name,
                IF(wa.Image is NULL, '', wa.Image) as Image,
                ca.UserID
            FROM collages_artists    AS ca
            INNER JOIN artists_group AS ag USING (ArtistID)
            LEFT JOIN wiki_artists   AS wa USING (RevisionID)
            WHERE ca.CollageID = ?
            ORDER BY ca.Sort
            ", $this->id
        );
        $artists = $this->db->to_array('ArtistID', MYSQLI_ASSOC);

        // synch collage total with reality
        $count = count($artists);
        if ($this->numEntries != $count) {
            $this->numEntries = $count;
            $this->db->prepared_query("
                UPDATE collages SET
                    NumTorrents = ?
                WHERE ID = ?
                ", $count, $this->id
            );
            $this->cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
            $this->cache->delete_value(sprintf('collage_%d', $this->id));
        }

        foreach ($artists as $artist) {
            if (!isset($this->artists[$artist['ArtistID']])) {
                $this->artists[$artist['ArtistID']] = [
                    'count' => 0,
                    'id'    => $artist['ArtistID'],
                    'image' => $artist['Image'],
                    'name'  => $artist['Name'],
                ];
            }
            $this->artists[$artist['ArtistID']]['count']++;

            if (!isset($this->contributors[$artist['UserID']])) {
                $this->contributors[$artist['UserID']] = 0;
            }
            $this->contributors[$artist['UserID']]++;
        }
        uasort($this->artists, function ($x, $y) { return $y['count'] <=> $x['count']; });
        arsort($this->contributors);
        return $this;
    }

    protected function loadTorrents() {
        $order = $this->sortNewest ? 'DESC' : 'ASC';
        $this->db->prepared_query("
            SELECT
                ct.GroupID,
                ct.UserID
            FROM collages_torrents AS ct
            INNER JOIN torrents_group AS tg ON (tg.ID = ct.GroupID)
            WHERE ct.CollageID = ?
            ORDER BY ct.Sort $order
            ", $this->id
        );
        $groupContribIds = $this->db->to_array('GroupID', MYSQLI_ASSOC);
        $groupIds = array_keys($groupContribIds);

        if (count($groupIds) > 0) {
            $this->torrents = \Torrents::get_groups($groupIds);
        } else {
            $this->torrents = [];
        }

        // synch collage total with reality
        $count = count($this->torrents);
        if ($this->numEntries != $count) {
            $this->numEntries = $count;
            $this->db->prepared_query("
                UPDATE collages SET
                    NumTorrents = ?
                WHERE ID = ?
                ", $count, $this->id
            );
            $this->cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
            $this->cache->delete_value(sprintf('collage_%d', $this->id));
        }

        // in case of a tie in tag usage counts, order by first past the post
        $this->db->prepared_query("
            SELECT count(*) as \"count\",
                tag.name AS tag
            FROM collages_torrents   AS ct
            INNER JOIN torrents_tags AS tt USING (groupid)
            INNER JOIN tags          AS tag ON (tag.id = tt.tagid)
            WHERE ct.collageid = ?
            GROUP BY tag.name
            ORDER BY 1 DESC, ct.AddedOn
            ", $this->id
        );
        $this->torrentTags = $this->db->to_array('tag', MYSQLI_ASSOC, false);

        $this->groupIds = [];
        foreach ($groupIds as $groupId) {
            if (!isset($this->torrents[$groupId])) {
                continue;
            }
            $this->groupIds[] = $groupId;
            $group = $this->torrents[$groupId];
            $extendedArtists = $group['ExtendedArtists'];
            $artists =
                (empty($extendedArtists[1]) && empty($extendedArtists[4]) && empty($extendedArtists[5]) && empty($extendedArtists[6]))
                ? $group['Artists']
                : array_merge((array)$extendedArtists[1], (array)$extendedArtists[4], (array)$extendedArtists[5], (array)$extendedArtists[6]);

            foreach ($artists as $artist) {
                if (!isset($this->artists[$artist['id']])) {
                    $this->artists[$artist['id']] = [
                        'count' => 0,
                        'id'    => (int)$artist['id'],
                        'name'  => $artist['name'],
                    ];
                }
                $this->artists[$artist['id']]['count']++;
            }

            $contribUserId = $groupContribIds[$groupId]['UserID'];
            if (!isset($this->contributors[$contribUserId])) {
                $this->contributors[$contribUserId] = 0;
            }
            $this->contributors[$contribUserId]++;
        }
        uasort($this->artists, function ($x, $y) { return $y['count'] <=> $x['count']; });
        arsort($this->contributors);
        return $this;
    }

    public function setViewer(int $userId) {
        $this->userId = $userId;
        $this->lockedForUser = false;
        if (!check_perms('site_collages_delete')) {
            if ($this->categoryId === '0') {
                if (!check_perms('site_collages_personal') || !$this->isOwner($userId)) {
                    $this->lockedForUser = true;
                }
            }
            $groupsByUser = $this->contributors[$userId] ?? 0;
            if ($this->locked
                || ($this->maxGroups > 0 && count($this->groupIds) >= $this->maxGroups)
                || ($this->maxGroupsPerUser > 0 && $groupsByUser >= $this->maxGroupsPerUser)
            ) {
                $this->lockedForUser = true;
            }
        }
        return $this;
    }

    public function categoryId() { return $this->categoryId; }
    public function description() { return $this->description; }
    public function groupIds() { return $this->groupIds; }
    public function isDeleted() { return $this->deleted == '1'; }
    public function isFeatured () { return $this->featured; }
    public function isLocked() { return $this->locked == '1' || (isset($this->lockedForUser) && $this->lockedForUser); }
    public function maxGroups() { return $this->maxGroups; }
    public function maxGroupsPerUser() { return $this->maxGroupsPerUser; }
    public function name() { return $this->name; }
    public function numArtists() { return count($this->artists); }
    public function numContributors() { return count(array_keys($this->contributors)); }
    public function numEntries() { return $this->numEntries; }
    public function numSubscribers() { return $this->numSubscribers; }
    public function ownerId() { return $this->ownerId; }
    public function sortNewest() { return $this->sortNewest; }
    public function tags() { return $this->tags; }
    public function updated() { return $this->updated; }
    public function contributors() { return $this->contributors; }

    public function isOwner(int $userId): bool { return $this->ownerId == $userId; }

    /**
     * Increment count of number of entries in collage.
     * @param int $delta change in value (defaults to 1)
     * @return int number of entries
     */
    public function increment(int $delta = 1): int {
        $this->db->prepared_query("
            UPDATE collages SET
                updated = now(),
                NumTorrents = greatest(0, NumTorrents + ?)
            WHERE ID = ?
            ", $delta, $this->id
        );
        return $this->numEntries = max(0, $this->numEntries + $delta);
    }

    /**
     * How many entries in this collage are owned by a given user
     * @param int $userId id of user
     * @return int number of entries
     */
    public function countByUser(string $table, int $userId): int {
        return $this->contributors[$userId] ?? 0;
    }

    /**
     * Flush the cache keys associated with this collage.
     */
    public function flush(array $keys = []) {
        $this->db->prepared_query("
            SELECT concat('collage_subs_user_new_', UserID) as k
            FROM users_collage_subs
            WHERE CollageID = ?
            ", $this->id
        );
        if ($this->db->has_results()) {
            $keys = array_merge($keys, $this->db->collect('k'));
        }
        $keys[] = sprintf(self::CACHE_KEY, $this->id);
        $this->cache->deleteMulti($keys);
        $this->cache->delete_value(sprintf('collage_%d', $this->id));
        return $this;
    }

    public function toggleSubscription(int $userId) {
        $this->db->set_query_id($qid);
        if ($this->db->scalar("
            SELECT 1
            FROM users_collage_subs
            WHERE UserID = ?
                AND CollageID = ?
            ", $userId, $this->id
        )) {
            $this->db->prepared_query("
                DELETE FROM users_collage_subs
                WHERE UserID = ?
                    AND CollageID = ?
                ", $userId, $this->id
            );
            $delta = -1;
        } else {
            $this->db->prepared_query("
                INSERT IGNORE INTO users_collage_subs
                       (UserID, CollageID)
                VALUES (?,      ?)
                ", $userId, $this->id
            );
            $delta = 1;
        }
        $this->db->prepared_query("
            UPDATE collages SET
                Subscribers = greatest(0, Subscribers + ?)
            WHERE ID = ?
            ", $delta, $this->id
        );
        $this->cache->deleteMulti([
            'collage_subs_user_' . $userId,
            'collage_subs_user_new_' . $userId,
            'collage_' . $this->id
        ]);
        $qid = $this->db->get_query_id();
        return $this;
    }

    /**
     * Load the subscriptions of the user, and clear the new additions flag if
     * they have subscribed to this collage.
     * @param int User id
     * @return int True if user is subscribed to this collage.
     */
    public function isSubscribed(int $userId): bool {
        $key = sprintf(self::SUBS_KEY, $userId);
        if (false === ($this->userSubscriptions = $this->cache->get_value($key))) {
            $this->db->prepared_query("
                SELECT CollageID
                FROM users_collage_subs
                WHERE UserID = ?
                ", $userId
            );
            $this->userSubscriptions = $this->db->collect(0);
            $this->cache->cache_value($key, $this->userSubscriptions, 3600 * 12);
        }
        if (!in_array($this->id, $this->userSubscriptions)) {
            return false;
        }

        $this->db->prepared_query("
            UPDATE users_collage_subs SET
                LastVisit = now()
            WHERE CollageID = ? AND UserID = ?
            ", $this->id, $userId
        );
        $this->cache->delete_value(sprintf(self::SUBS_NEW_KEY, $userId));
        return true;
    }

    /*** ARTIST COLLAGES ***/

    /**
     * Get artists of a collage for display.
     */
    public function artistList() {
        return $this->artists;
    }

    /**
     * Does the artist already exist in this artist collage
     * @return boolean true if artist is already present
     */
    public function hasArtist(int $artistId): bool {
        $this->db->prepared_query("
            SELECT 1
            FROM collages_artists
            WHERE CollageID = ?  AND ArtistID = ?
            ", $this->id, $artistId
        );
        return $this->db->has_results();
    }

    /**
     * Add an artist to an artist collage.
     */
    public function addArtist(int $artistId, int $adderId) {
        if ($this->hasArtist($artistId)) {
            return;
        }
        $this->db->prepared_query("
            INSERT IGNORE INTO collages_artists
                   (CollageID, ArtistID, UserID, Sort)
            VALUES (?,         ?,        ?,
                (SELECT coalesce(max(ca.Sort), 0) + 10 FROM collages_artists ca WHERE ca.CollageID = ?)
            )
            ",  $this->id, $artistId, $adderId, $this->id
        );
        $this->increment($this->db->affected_rows());
        $this->flush([
            "artists_collages_$artistId",
            "artists_collages_personal_$artistId"
        ]);
        return $this;
    }

    /**
     * Remove an artist from an artist collage
     */
   public function removeArtist(int $artistId) {
        $this->db->prepared_query("
            DELETE FROM collages_artists
            WHERE CollageID = ?
                AND ArtistID = ?
            ", $this->id, $artistId
        );
        $this->increment(-$this->db->affected_rows());
        $this->flush([
            "artists_collages_$artistId",
            "artists_collages_personal_$artistId"
        ]);
        return $this;
   }

    /*** TORRENT COLLAGES ***/

    /**
     * Get torrents of a collage to display
     * In order to display the collage correctly, the code needs to know who is viewing the
     * collage. The method setUserContext() must be called prior to calling this method,
     * otherwise it will throw an exception.
     *
     * @param ascending int True to sort ASC, False to sort DESC
     * @return an of torrent groups, and an array of user ids (who added the torrents)
     */
    public function torrentList(): array {
        if (is_null($this->userId)) {
            throw new Exception\CollageUserNotSetException;
        }
        return $this->torrents;
    }

    /**
     * Does the torrent already exist in this torrent collage
     * @return boolean true if torrent is already present
     */
    public function hasTorrent(int $groupId): bool {
        $this->db->prepared_query("
            SELECT 1
            FROM collages_torrents
            WHERE CollageID = ?  AND GroupID = ?
            ", $this->id, $groupId
        );
        return $this->db->has_results();
    }

    /**
     * Add an torrent group to an torrent collage.
     * @param int $groupId id of torrent group
     */
    public function addTorrent(int $groupId, int $adderId) {
        if ($this->hasTorrent($groupId)) {
            return;
        }
        $this->db->prepared_query("
            INSERT IGNORE INTO collages_torrents
                   (CollageID, GroupID, UserID, Sort)
            VALUES (?,         ?,       ?,
                (SELECT coalesce(max(ct.Sort), 0) + 10 FROM collages_torrents ct WHERE ct.CollageID = ?)
            )
            ",  $this->id, $groupId, $adderId,
                $this->id
        );
        $this->increment();
        $this->flush([
            "torrents_collages_$groupId",
            "torrents_collages_personal_$groupId",
            "torrents_details_$groupId"
        ]);
        return $this;
    }

    /** Get top artists of the collage
     * @param int limit Number of entries to return (default 5, -1 for all)
     * @return array associative array of artist ids, pointing to number of entries by artist
     */
    public function topArtists(int $limit = 5): array {
        return $limit == -1
            ? $this->artists
            : array_slice($this->artists, 0, $limit);
    }

    /** Get top tags of the collage
     * @param int limit Number of entries to return (default 5, -1 for all)
     * @return array associative array of tags, pointing to number of occurrences (descending) by tag
     */
    public function topTags(int $limit = 5): array {
        return $limit == -1
            ? $this->torrentTags
            : array_slice($this->torrentTags, 0, $limit, true);
    }
}
