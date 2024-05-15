<?php

namespace Gazelle;

use Gazelle\Enum\CollageType;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class Collage extends BaseObject {
    /**
     * A Gazelle\Collage is a holder object that delegates most functionality to
     * an underlying Gazelle\Collage\AbstractCollage object. The latter knows
     * how to add and remove entries to the associated tables underneath
     * (artists or torrent groups).
     */

    final public const tableName    = 'collages';
    final public const CACHE_KEY    = 'collagev4_%d';
    final public const SUBS_KEY     = 'collage_subs_user_%d';
    final public const SUBS_NEW_KEY = 'collage_subs_user_new_%d';

    protected bool  $lockedForUser = false;
    protected array $userSubscriptions;
    protected User  $viewer;

    protected Collage\AbstractCollage $collage;

    public function __construct(int $id, int $categoryId) {
        parent::__construct($id);
        $this->collage = $categoryId === CollageType::artist->value
            ? new Collage\Artist($this)
            : new Collage\TGroup($this);
        $this->collage->load();
    }

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->userSubscriptions);
        unset($this->info);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name())); }
    public function location(): string { return 'collages.php?id=' . $this->id; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT c.Deleted       AS is_deleted,
                    c.TagList          AS tag_string,
                    c.UserID           AS user_id,
                    c.CategoryID       AS category_id,
                    c.Updated          AS updated,
                    c.Subscribers      AS subscriber_total,
                    c.NumTorrents      AS torrent_total,
                    c.MaxGroups        AS group_max,
                    c.MaxGroupsPerUser AS group_max_per_user,
                    c.Locked           AS is_locked,
                    c.Name             AS name,
                    c.Description      AS description,
                    c.Featured         AS is_featured
                FROM collages c
                WHERE c.ID = ?
                ", $this->id
            );
            $info['tag_list'] = explode(' ', $info['tag_string']);

            self::$db->prepared_query("
                SELECT ca.Name, ca.ID
                FROM collage_attr ca
                INNER JOIN collage_has_attr cha ON (cha.CollageAttrID = ca.ID)
                WHERE cha.CollageID = ?
                ", $this->id
            );
            $info['attr'] = self::$db->to_pair('Name', 'ID', false);
            self::$cache->cache_value($key, $info, 7200);
        }
        $this->info = $info;
        return $this->info;
    }

    public function categoryId(): int { return $this->info()['category_id']; }
    public function description(): string { return $this->info()['description']; }
    public function maxGroups(): int { return $this->info()['group_max']; }
    public function maxGroupsPerUser(): int { return $this->info()['group_max_per_user']; }
    public function name(): string { return $this->info()['name']; }
    public function numSubscribers(): int { return $this->info()['subscriber_total']; }
    public function ownerId(): int { return $this->info()['user_id']; }
    public function tags(): array { return $this->info()['tag_list']; }
    public function updated(): ?string { return $this->info()['updated']; }

    public function numEntries(): int { return $this->info()['torrent_total']; }
    public function groupIds(): array { return $this->collage->groupIdList(); /** @phpstan-ignore-line */ }

    public function isDeleted(): bool { return $this->info()['is_deleted'] === '1'; }
    public function isFeatured(): bool { return (bool)$this->info()['is_featured']; }
    public function isLocked(): bool { return $this->info()['is_locked'] == '1' || $this->lockedForUser; }
    public function isOwner(User $user): bool { return $this->info()['user_id'] === $user->id(); }
    public function isPersonal(): bool { return $this->info()['category_id'] === CollageType::personal->value; }

    public function isArtist(): bool { return $this->categoryId() === CollageType::artist->value; }
    public function contributors(): array { return $this->collage->contributorList(); }

    public function numContributors(): int { return count(array_keys($this->contributors())); }
    public function numArtists(): int { return count($this->collage->artistList()); }
    public function sequence(int $entryId): int { return $this->collage->sequence($entryId); /** @phpstan-ignore-line */ }

    public function hasAttr(string $name): bool {
        return isset($this->info()['attr'][$name]);
    }
    public function sortNewest(): bool { return $this->hasAttr('sort-newest'); }

    public function setViewer(User $viewer): static {
        $this->viewer = $viewer;
        $this->lockedForUser = false;
        if (!$this->viewer->permitted('site_collages_delete')) {
            if ($this->categoryId() === 0) {
                if (!$this->viewer->permitted('site_collages_personal') || !$this->isOwner($this->viewer)) {
                    $this->lockedForUser = true;
                }
            }
            $groupsByUser = $this->contributors()[$this->viewer->id()] ?? 0;
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
        return isset($this->contributors()[$user->id()]);
    }

    public function userCanContribute(User $user): bool {
        return !$this->isLocked()
            && (
                (!$this->isPersonal() && ($user->permitted('site_collages_manage') || $user->activePersonalCollages()))
                ||
                ($this->isPersonal() && $this->isOwner($user))
            );
    }

    /**
     * How many entries in this collage are owned by a given user
     */
    public function contributionTotal(User $user): int {
        return $this->contributors()[$user->id()] ?? 0;
    }

    public function entryCreated(int $entryId): string {
        return $this->collage->entryCreated($entryId);
    }

    public function entryUserId(int $entryId): int {
        return $this->collage->entryUserId($entryId);
    }

    public function toggleSubscription(User $user): int {
        $affected = 0;
        if ((bool)self::$db->scalar("
            SELECT 1
            FROM users_collage_subs
            WHERE UserID = ?
                AND CollageID = ?
            ", $user->id(), $this->id
        )) {
            self::$db->prepared_query("
                DELETE FROM users_collage_subs
                WHERE UserID = ?
                    AND CollageID = ?
                ", $user->id(), $this->id
            );
            $affected = self::$db->affected_rows();
            if (isset($this->userSubscriptions)) {
                unset($this->userSubscriptions[$user->id()]);
            }
            $delta = -1;
        } else {
            self::$db->prepared_query("
                INSERT IGNORE INTO users_collage_subs
                       (UserID, CollageID)
                VALUES (?,      ?)
                ", $user->id(), $this->id
            );
            $affected = self::$db->affected_rows();
            $delta = 1;
        }
        if ($affected) {
            self::$db->prepared_query("
                UPDATE collages SET
                    Subscribers = greatest(0, Subscribers + ?)
                WHERE ID = ?
                ", $delta, $this->id
            );
            $this->flush();
            self::$cache->delete_multi([
                sprintf(self::SUBS_KEY, $user->id()),
                sprintf(self::SUBS_NEW_KEY, $user->id()),
            ]);
        }
        return $affected;
    }

    /**
     * Load the subscriptions of the user, and clear the new additions flag if
     * they have subscribed to this collage.
     */
    public function isSubscribed(User $user): bool {
        if (!isset($this->userSubscriptions)) {
            $key = sprintf(self::SUBS_KEY, $user->id());
            $subs = self::$cache->get_value($key);
            if ($subs === false) {
                self::$db->prepared_query("
                    SELECT CollageID FROM users_collage_subs WHERE UserID = ?
                    ", $user->id()
                );
                $subs = self::$db->collect(0, false);
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
            ", $this->id, $user->id()
        );
        self::$cache->delete_value(sprintf(self::SUBS_NEW_KEY, $user->id()));
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

    /**
     * Get artists names of a collage for the ajax representation.
     */
    public function nameList(): array {
        return $this->collage->nameList(); /** @phpstan-ignore-line */
    }

    /*** TORRENT COLLAGES ***/

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
            ? $this->collage->torrentTagList() /** @phpstan-ignore-line */
            : array_slice($this->collage->torrentTagList(), 0, $limit, true); /** @phpstan-ignore-line */
    }

    /**
     * Return a list of all the FLAC torrents in all the groups in this collage.
     * No need to cache, because it will only ever be called by a moderator when
     * freeleeching a collage
     */
    public function entryFlacList(): array {
        self::$db->prepared_query("
            SELECT t.ID
            FROM collages_torrents ct
            INNER JOIN torrents t  USING (GroupID)
            WHERE t.Format = ?
                AND ct.collageid = ?
            ", 'FLAC', $this->id
        );
        return self::$db->collect(0, false);
    }

    public function entryAllList(): array {
        self::$db->prepared_query("
            SELECT t.ID
            FROM collages_torrents ct
            INNER JOIN torrents t  USING (GroupID)
            WHERE ct.collageid = ?
            ", $this->id
        );
        return self::$db->collect(0, false);
    }

    public function setFreeleech(
        \Gazelle\Manager\Torrent $torMan,
        \Gazelle\Tracker         $tracker,
        \Gazelle\User            $user,
        LeechType                $leechType,
        LeechReason              $reason,
        int                      $threshold = 0,
        bool                     $all       = false,
    ): int {
        $regular = [];
        $large   = [];
        $idList  = $all ? $this->entryAllList() : $this->entryFlacList();
        foreach ($idList as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if ($threshold > 0 and $torrent->size() > $threshold) {
                $large[] = $torrent->id();
            } else {
                $regular[] = $torrent->id();
            }
        }
        if ($regular) {
            $torMan->setListFreeleech($tracker, $user, $regular, $leechType, $reason);
        }
        if ($large) {
            $torMan->setListFreeleech($tracker, $user, $large, LeechType::Neutral, $reason);
        }
        return count($regular) + count($large);
    }

    /*** UPDATE METHODS ***/

    public function addEntry(int $entryId, User $user): int {
        return $this->collage->addEntry($entryId, $user);
    }

    public function hasEntry(int $entryId): bool {
        return $this->collage->hasEntry($entryId);
    }

    public function removeEntry(int $entryId): int {
        return $this->collage->removeEntry($entryId);
    }

    public function rebuildTagList(): array {
        return $this->collage->rebuildTagList();
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

    public function toggleLocked(): static {
        return $this->setField('Locked', $this->isLocked() ? '0' : '1');
    }

    public function setFeatured(): static {
        return $this->setField('Featured', 1);
    }

    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        if ($this->field('Featured')) {
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

    public function toggleAttr(string $attr, bool $flag): bool {
        $hasAttr = $this->hasAttr($attr);
        $toggled = false;
        if (!$flag && $hasAttr) {
            self::$db->prepared_query("
                DELETE FROM collage_has_attr
                WHERE CollageID = ?
                    AND CollageAttrID = (SELECT ID FROM collage_attr WHERE Name = ?)
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        } elseif ($flag && !$hasAttr) {
            self::$db->prepared_query("
                INSERT INTO collage_has_attr (CollageID, CollageAttrID)
                    SELECT ?, ID FROM collage_attr WHERE Name = ?
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        }
        if ($toggled) {
            $this->flush();
        }
        return $toggled;
    }

    public function hardRemove(): int {
        self::$db->prepared_query("
            DELETE c, ca, ct
            FROM collages c
            LEFT JOIN collages_artists ca ON (ca.CollageID = c.ID)
            LEFT JOIN collages_torrents ct ON (ct.CollageID = c.ID)
            WHERE c.ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->id),
            sprintf(Manager\Collage::ID_KEY, $this->id),
        ]);
        return $affected;
    }
}
