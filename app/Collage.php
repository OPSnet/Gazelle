<?php

namespace Gazelle;

class Collage extends BaseObject {

    /**
     * A Gazelle\Collage is a holder object that delegates most functionality to
     * an underlying Gazelle\Collage\AbstractCollage object. The latter knows
     * how to add and remove entries to the associated tables underneath
     * (artists or torrent groups).
     */

    const CACHE_KEY    = 'collagev2_%d';
    const SUBS_KEY     = 'collage_subs_user_%d';
    const SUBS_NEW_KEY = 'collage_subs_user_new_%d';

    protected bool  $lockedForUser = false;
    protected array $info;
    protected array $userSubscriptions;
    protected User  $viewer;

    protected Collage\AbstractCollage $collage;

    public function __construct(int $id) {
        parent::__construct($id);
        // NB: There is a chicken-and-egg problem here which could be made clearer
        if ($this->isArtist()) {
            $this->collage = new Collage\Artist($this);
        } else {
            $this->collage = new Collage\TGroup($this);
        }
        $this->collage->load();
    }

    public function tableName(): string { return 'collages'; }

    public function url(): string {
        return 'collages.php?id=' . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name()));
    }

    public function flush() {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
    }

    public function categoryId() { return $this->info()['category_id']; }
    public function description() { return $this->info()['description']; }
    public function maxGroups() { return $this->info()['group_max']; }
    public function maxGroupsPerUser() { return $this->info()['group_max_per_user']; }
    public function name() { return $this->info()['name']; }
    public function numSubscribers() { return $this->info()['subscriber_total']; }
    public function ownerId() { return $this->info()['user_id']; }
    public function sortNewest() { return $this->info()['sort_newest']; }
    public function tags() { return $this->info()['tag_list']; }
    public function updated() { return $this->info()['updated']; }

    public function numEntries() { return $this->info()['torrent_total']; }
    public function groupIds() { return $this->collage->groupIdList(); }

    public function isDeleted(): bool { return $this->info()['is_deleted'] === '1'; }
    public function isFeatured(): bool { return (bool)$this->info()['is_featured']; }
    public function isLocked(): bool { return $this->info()['is_locked'] == '1' || $this->lockedForUser; }
    public function isOwner(int $userId): bool { return $this->info()['user_id'] === $userId; }
    public function isPersonal(): bool { return $this->info()['category_id'] === 0; }

    public function isArtist(): bool { return $this->categoryId() === COLLAGE_ARTISTS_ID; }
    public function contributors() { return $this->collage->contributorList(); }

    public function numContributors() { return count(array_keys($this->contributors())); }
    public function numArtists() { return count($this->collage->artistList()); }

    public function info(): array {
        if (!isset($this->info)) {
            $key = sprintf(self::CACHE_KEY, $this->id);
            $info = self::$cache->get_value($key);
            if ($info === false) {
                $info = self::$db->rowAssoc("
                    SELECT c.Deleted        AS is_deleted,
                        c.TagList           AS tag_string,
                        c.UserID            AS user_id,
                        c.CategoryID        AS category_id,
                        c.Updated           AS updated,
                        c.Subscribers       AS subscriber_total,
                        c.NumTorrents       AS torrent_total,
                        c.MaxGroups         AS group_max,
                        c.MaxGroupsPerUser  AS group_max_per_user,
                        c.Locked            AS is_locked,
                        c.Name              AS name,
                        c.Description       AS description,
                        c.Featured          AS is_featured,
                        CASE WHEN cha.CollageID IS NULL THEN 0 ELSE 1 END AS sort_newest
                    FROM collages c
                    LEFT JOIN collage_has_attr cha ON (cha.CollageID = c.ID)
                    LEFT JOIN collage_attr ca ON (ca.ID = cha.CollageAttrID and ca.Name = ?)
                    WHERE c.ID = ?
                    ", 'sort-newest', $this->id
                );
                $info['tag_list'] = explode(' ', $info['tag_string']);
                self::$cache->cache_value($key, $info, 7200);
            }
            $this->info = $info;
        }
        return $this->info;
    }

    public function setViewer(User $viewer) {
        $this->viewer = $viewer;
        $this->lockedForUser = false;
        if (!$this->viewer->permitted('site_collages_delete')) {
            if ($this->categoryId() === '0') {
                if (!$this->viewer->permitted('site_collages_personal') || !$this->isOwner($this->viewer->id())) {
                    $this->lockedForUser = true;
                }
            }
            $groupsByUser = $this->contributors[$this->viewer->id()] ?? 0;
            if ($this->isLocked()
                || ($this->maxGroups() > 0 && count($this->groupIds()) >= $this->maxGroups())
                || ($this->maxGroupsPerUser() > 0 && $groupsByUser >= $this->maxGroupsPerUser())
            ) {
                $this->lockedForUser = true;
            }
        }
        return $this;
    }

    public function userHasContributed(User $user): bool {
        return isset($this->contributors[$user->id()]);
    }

    public function userCanContribute(User $user): bool {
        return !$this->isLocked()
            && (
                (!$this->isPersonal() && ($user->permitted('site_collages_manage') || $user->activePersonalCollages()))
                ||
                ($this->isPersonal() && $this->isOwner($user->id()))
            );
    }

    /**
     * How many entries in this collage are owned by a given user
     */
    public function countByUser(int $userId): int {
        return $this->contributors[$userId] ?? 0;
    }

    public function entryUserId(int $entryId): int {
        return $this->collage->entryUserId($entryId);
    }

    public function toggleSubscription(int $userId) {
        $qid = self::$db->get_query_id();
        $delta = 0;
        if (self::$db->scalar("
            SELECT 1
            FROM users_collage_subs
            WHERE UserID = ?
                AND CollageID = ?
            ", $userId, $this->id
        )) {
            self::$db->prepared_query("
                DELETE FROM users_collage_subs
                WHERE UserID = ?
                    AND CollageID = ?
                ", $userId, $this->id
            );
            $delta = -1;
        } else {
            self::$db->prepared_query("
                INSERT IGNORE INTO users_collage_subs
                       (UserID, CollageID)
                VALUES (?,      ?)
                ", $userId, $this->id
            );
            $delta = 1;
        }
        if ($delta !== 0) {
            self::$db->prepared_query("
                UPDATE collages SET
                    Subscribers = greatest(0, Subscribers + ?)
                WHERE ID = ?
                ", $delta, $this->id
            );
            self::$cache->deleteMulti([
                sprintf(self::SUBS_KEY, $userId),
                sprintf(self::SUBS_NEW_KEY, $userId),
            ]);
        }
        $qid = self::$db->get_query_id();
        return $this;
    }

    /**
     * Load the subscriptions of the user, and clear the new additions flag if
     * they have subscribed to this collage.
     */
    public function isSubscribed(int $userId): bool {
        if (empty($this->userSubscriptions)) {
            $key = sprintf(self::SUBS_KEY, $userId);
            $subs = self::$cache->get_value($key);
            if ($subs ===false) {
                self::$db->prepared_query("
                    SELECT CollageID FROM users_collage_subs WHERE UserID = ?
                    ", $userId
                );
                $subs = self::$db->collect(0);
                self::$cache->cache_value($key, $subs, 3600 * 12);
            }
            $this->userSubscriptions = $subs;
        }
        if (!in_array($this->id, $this->userSubscriptions)) {
            return false;
        }

        self::$db->prepared_query("
            UPDATE users_collage_subs SET
                LastVisit = now()
            WHERE CollageID = ? AND UserID = ?
            ", $this->id, $userId
        );
        self::$cache->delete_value(sprintf(self::SUBS_NEW_KEY, $userId));
        return true;
    }

    public function entryList(): array {
        return $this->collage->entryList();
    }

    /*** ARTIST COLLAGES ***/

    /**
     * Get artists of a collage for display.
     */
    public function artistList(): array {
        return $this->collage->artistList();
    }

    /*** TORRENT COLLAGES ***/

    /**
     * Get torrents of a collage to display
     */
    public function torrentList(): array {
        return $this->collage->torrentList();
    }

    /** Get top artists of the collage
     * @param int $limit Number of entries to return (default 5, -1 for all)
     */
    public function topArtists(int $limit = 5): array {
        return $limit == -1
            ? $this->collage->artistList()
            : array_slice($this->collage->artistList(), 0, $limit);
    }

    /** Get top tags of the collage
     * @param int $limit Number of entries to return (default 5, -1 for all)
     */
    public function topTags(int $limit = 5): array {
        return $limit == -1
            ? $this->collage->torrentTagList()
            : array_slice($this->collage->torrentTagList(), 0, $limit, true);
    }

    /*** UPDATE METHODS ***/

    public function addEntry(int $entryId, int $userId): int {
        return $this->collage->addEntry($entryId, $userId);
    }

    public function removeEntry(int $entryId): int {
        return $this->collage->removeEntry($entryId);
    }

    public function updateSequence(string $series): int {
        return $this->collage->updateSequence($series);
    }

    public function updateSequenceEntry(int $entryId, int $sequence): int {
        return $this->collage->updateSequenceEntry($entryId, $sequence);
    }

    public function remove(): int {
        return $this->collage->remove();
    }

    public function setToggleLocked() {
        return $this->setUpdate('Locked', $this->isLocked() === '1' ? '0' : '1');
    }

    public function setFeatured() {
        return $this->setUpdate('Featured', 1);
    }

    public function modify(): bool {
        if (!$this->updateField) {
            return false;
        }
        if (in_array('Featured', $this->updateField)) {
            // unfeature the previously featured collage
            self::$db->prepared_query("
                UPDATE collages SET
                    Featured = 0
                WHERE CategoryID = 0
                    AND Featured = 1
                    AND UserID = ?
                ", $this->ownerId()
            );
        }
        return parent::modify();
    }
}
