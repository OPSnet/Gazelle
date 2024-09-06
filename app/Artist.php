<?php

namespace Gazelle;

use Gazelle\Intf\CollageEntry;

class Artist extends BaseObject implements CollageEntry {
    final public const pkName               = 'ArtistID';
    final public const tableName            = 'artists_group';
    final public const CACHE_REQUEST_ARTIST = 'artists_requests_%d';

    protected const CACHE_PREFIX    = 'artist_%d';

    protected array $artistRole;

    /** All the groups */
    protected array $group = [];

    /** The roles an artist holds in a release */
    protected array $groupRole = [];

    /** Their groups, gathered into sections */
    protected array $section = [];

    protected Stats\Artist $stats;
    protected Artist\Similar $similar;

    public function __construct(
        protected int $id,
        protected ?int $aliasId = null,
        protected int $revisionId = 0
    ) {}

    protected function cacheKey(): string {
        return sprintf(self::CACHE_PREFIX, $this->id)
            . ($this->revisionId ? '_r' . $this->revisionId : '');
    }

    public function flush(): static {
        self::$db->prepared_query("
            SELECT DISTINCT concat('groups_artists_', GroupID)
            FROM torrents_artists ta
            INNER JOIN artists_alias aa USING (AliasID)
            WHERE aa.ArtistID = ?
            ", $this->id
        );
        self::$cache->delete_multi([
            $this->cacheKey(),
            sprintf(self::CACHE_REQUEST_ARTIST, $this->id),
            ...self::$db->collect(0, false)
        ]);
        unset($this->info);
        return $this;
    }

    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name())); }
    public function location(): string { return 'artist.php?id=' . $this->id; }

    public function info(): array {
        $cacheKey = $this->cacheKey();
        $info = self::$cache->get_value($cacheKey);
        if ($info !== false) {
            $this->info = $info;
        } else {
            $sql = "
            SELECT ag.PrimaryAlias   AS primary_alias_id,
                wa.Image             AS image,
                wa.body              AS body,
                ag.VanityHouse       AS showcase,
                dg.artist_discogs_id AS discogs_id,
                dg.name              AS discogs_name,
                dg.stem              AS discogs_stem,
                dg.sequence,
                dg.is_preferred
            FROM artists_group AS ag
            LEFT JOIN artist_discogs AS dg ON (dg.artist_id = ag.ArtistID)
            ";
            if ($this->revisionId) {
                $sql .= "LEFT JOIN wiki_artists AS wa ON (wa.PageID = ag.ArtistID)";
                $cond = 'wa.RevisionID = ?';
                $args = [$this->revisionId];
            } else {
                $sql .= "LEFT JOIN wiki_artists AS wa USING (RevisionID)";
                $cond = 'ag.ArtistID = ?';
                $args = [$this->id];
            }
            $sql .= " WHERE $cond";
            $info = self::$db->rowAssoc($sql, ...$args);

            self::$db->prepared_query("
                SELECT AliasID AS alias_id,
                    Redirect   AS redirect_id,
                    Name       AS name
                FROM artists_alias
                WHERE ArtistID = ?
                ", $this->id
            );
            $info['alias'] = self::$db->to_array('alias_id', MYSQLI_ASSOC, false);

            self::$db->prepared_query("
                SELECT aa.name, aa.artist_attr_id
                FROM artist_attr aa
                INNER JOIN artist_has_attr aha USING (artist_attr_id)
                WHERE aha.artist_id = ?
                ", $this->id
            );
            $info['attr'] = self::$db->to_pair('name', 'artist_attr_id', false);

            $info['homonyms'] = (int)self::$db->scalar('
                SELECT count(*) FROM artist_discogs WHERE stem = ?
                ', $info['discogs_stem']
            );

            self::$cache->cache_value($cacheKey, $info, 3600);
            $this->info = $info;
        }

        // hydrate the Discogs object
        $this->info['discogs'] = new Util\Discogs(
            id:       (int)$this->info['discogs_id'],
            sequence: (int)$this->info['sequence'],
            name:     (string)$this->info['discogs_name'],
            stem:     (string)$this->info['discogs_stem'],
        );
        return $this->info;
    }

    public function loadArtistRole(): static {
        self::$db->prepared_query("
            SELECT ta.GroupID AS group_id,
                ta.Importance as artist_role,
                rt.ID as release_type_id
            FROM torrents_artists AS ta
            INNER JOIN torrents_group AS tg ON (tg.ID = ta.GroupID)
            INNER JOIN release_type AS rt ON (rt.ID = tg.ReleaseType)
            INNER JOIN artists_alias aa ON (ta.AliasID = aa.AliasID)
            WHERE aa.ArtistID = ?
            ORDER BY tg.Year DESC, tg.Name, rt.ID
            ", $this->id
        );
        $this->artistRole = [
            ARTIST_MAIN => 0,
            ARTIST_GUEST => 0,
            ARTIST_REMIXER => 0,
            ARTIST_COMPOSER => 0,
            ARTIST_CONDUCTOR => 0,
            ARTIST_DJ => 0,
            ARTIST_PRODUCER => 0,
            ARTIST_ARRANGER => 0,
        ];

        while ([$groupId, $role, $releaseTypeId] = self::$db->next_record(MYSQLI_NUM, false)) {
            $role = (int)$role;
            $sectionId = match ($role) {
                ARTIST_ARRANGER => ARTIST_SECTION_ARRANGER,
                ARTIST_PRODUCER => ARTIST_SECTION_PRODUCER,
                ARTIST_COMPOSER => ARTIST_SECTION_COMPOSER,
                ARTIST_REMIXER => ARTIST_SECTION_REMIXER,
                ARTIST_GUEST => ARTIST_SECTION_GUEST,
                default => $releaseTypeId,
            };
            if (!isset($this->section[$sectionId])) {
                $this->section[$sectionId] = [];
            }
            $this->section[$sectionId][$groupId] = true;
            if (!isset($this->groupRole[$groupId])) {
                $this->groupRole[$groupId] = [];
            }
            $this->groupRole[$groupId][] = $role;
            ++$this->artistRole[$role];
        }
        return $this;
    }

    public function aliasId(): int {
        return $this->aliasId ?? $this->primaryAliasId();
    }

    public function artistRole(): array {
        if (!isset($this->artistRole)) {
            $this->loadArtistRole();
        }
        return $this->artistRole;
    }

    public function body(): ?string {
        return $this->info()['body'];
    }

    public function discogs(): Util\Discogs {
        return $this->info()['discogs'];
    }

    public function discogsIsPreferred(): bool {
        return $this->info()['is_preferred'];
    }

    public function groupIds(): array {
        if (!isset($this->groupIds)) {
            $this->loadArtistRole();
        }
        return array_keys($this->groupRole);
    }

    public function group(int $groupId): array {
        if (!isset($this->group)) {
            $this->loadArtistRole();
        }
        return $this->group[$groupId] ?? []; // FIXME
    }

    public function homonymCount(): int {
        return $this->info()['homonyms'];
    }

    public function image(): ?string {
        return $this->info()['image'];
    }

    public function isLocked(): bool {
        return $this->hasAttr('locked');
    }

    public function isShowcase(): bool {
        return $this->info()['showcase'] == 1;
    }

    public function label(): string {
        return "{$this->id} ({$this->name()})";
    }

    public function name(): string {
        return $this->aliasList()[$this->aliasId()]['name'];
    }

    public function primaryAliasId(): int {
        return $this->info()['primary_alias_id'];
    }

    public function sections(): array {
        if (!isset($this->section)) {
            $this->loadArtistRole();
        }
        return $this->section;
    }

    public function similar(): Artist\Similar {
        return $this->similar ??= new Artist\Similar($this);
    }

    public function stats(): Stats\Artist {
        if (!isset($this->stats)) {
            $this->stats = new Stats\Artist($this->id);
        }
        return $this->stats;
    }

    public function hasAttr(string $name): bool {
        return isset($this->info()['attr'][$name]);
    }

    public function toggleAttr(string $attr, bool $flag): bool {
        $hasAttr = $this->hasAttr($attr);
        $toggled = false;
        if (!$flag && $hasAttr) {
            self::$db->prepared_query("
                DELETE FROM artist_has_attr
                WHERE artist_id = ?
                    AND artist_attr_id = (SELECT artist_attr_id FROM artist_attr WHERE name = ?)
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        } elseif ($flag && !$hasAttr) {
            self::$db->prepared_query("
                INSERT INTO artist_has_attr (artist_id, artist_attr_id)
                    SELECT ?, artist_attr_id FROM artist_attr WHERE name = ?
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        }
        if ($toggled) {
            $this->flush();
        }
        return $toggled;
    }

    public function createRevision(
        ?string $body,
        ?string $image,
        array   $summary,
        User    $user,
    ): int {
        self::$db->prepared_query("
            INSERT INTO wiki_artists
                   (PageID, Body, Image, UserID, Summary)
            VALUES (?,      ?,    ?,     ?,      ?)
            ", $this->id, $body, $image, $user->id(),
                implode(', ', array_filter($summary, fn($s) => !empty($s)))
        );
        $revisionId = self::$db->inserted_id();
        self::$db->prepared_query("
            UPDATE artists_group SET
                RevisionID = ?
            WHERE ArtistID = ?
            ", $revisionId, $this->id
        );
        $this->flush();
        return $revisionId;
    }

    /**
     * Revert to a prior revision of the artist metadata
     * (Which also creates a new revision).
     */
    public function revertRevision(int $revisionId, \Gazelle\User $user): int {
        self::$db->prepared_query("
            INSERT INTO wiki_artists
                  (Body, Image, PageID, UserID, Summary)
            SELECT Body, Image, ?,      ?,      ?
            FROM wiki_artists
            WHERE RevisionID = ?
            ", $this->id, $user->id(), "Reverted to revision $revisionId",
                $revisionId
        );
        $newRevId = self::$db->inserted_id();
        self::$db->prepared_query("
            UPDATE artists_group SET
                RevisionID = ?
            WHERE ArtistID = ?
            ", $newRevId, $this->id
        );
        $this->flush();
        return $newRevId;
    }

    public function revisionList(): array {
         self::$db->prepared_query("
            SELECT RevisionID AS revision,
                Summary       AS summary,
                Time          AS time,
                UserID        AS user_id
            FROM wiki_artists
            WHERE PageID = ?
            ORDER BY RevisionID DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function tagLeaderboard(): array {
        self::$db->prepared_query("
            SELECT t.Name AS name,
                count(*)  AS total
            FROM torrents_artists ta
            INNER JOIN torrents_group tg ON (tg.ID = ta.GroupID)
            INNER JOIN torrents_tags tt USING (GroupID)
            INNER JOIN tags t ON (t.ID = tt.TagID)
            INNER JOIN artists_alias aa ON (ta.AliasID = aa.AliasID)
            WHERE tg.CategoryID NOT IN (3, 7)
                AND aa.ArtistID = ?
            GROUP BY t.Name
            ORDER By 2 desc, t.Name
            LIMIT 10
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function addAlias(string $name, ?int $redirect, User $user, Log $logger): int {
        self::$db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, Redirect, UserID)
            VALUES (?,        ?,    ?,        ?)
            ", $this->id, $name, $redirect ?? 0, $user->id()
        );
        $aliasId = self::$db->inserted_id();
        $logger->general(
            "The alias $aliasId ($name) was added to the artist {$this->label()} by user {$user->label()}"
        );
        $this->flush();
        return $aliasId;
    }

    public function getAlias($name): ?int {
        $alias = array_keys(
            array_filter(
                $this->aliasList(),
                fn($a) => (strcasecmp($a['name'], $name) == 0)
            )
        );
        return empty($alias) ? null : current($alias);  // @phpstan-ignore-line ?phpstan bug should return int|null but returns int|string|null.
    }

    public function removeAlias(int $aliasId, User $user, Log $logger): int {
        if ($this->aliasId === $aliasId) {
            $this->aliasId = $this->primaryAliasId();
        }
        $alias = $this->aliasList()[$aliasId];
        self::$db->prepared_query("
            DELETE FROM artists_alias WHERE AliasID = ?
            ", $aliasId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->flush();
            $logger->general(
                "The alias $aliasId ({$alias['name']}) for the artist {$this->label()}  was removed by user {$user->label()}"
            );
        }
        return $affected;
    }

    public function aliasList(): array {
        return $this->info()['alias'];
    }

    public function aliasNameList(): array {
        return array_values(array_map(fn($a) => $a['name'], $this->aliasList()));
    }

    /**
     * Build the alias info. We want all the non-redirecting aliases at the top
     * level, and gather their aliases together, and having everything sorted
     * alphabetically.
     *  +---------+-----------+------------+
     *  | aliasId | aliasName | redirectId |
     *  +---------+-----------+------------+
     *  |     136 | alpha     |          0 |
     *  |      82 | bravo     |          0 |
     *  |     120 | charlie   |          0 |
     *  |     122 | delta     |          0 |
     *  |     134 | echo      |         82 |
     *  |     135 | foxtrot   |        122 |
     *  |     140 | india     |        136 |
     *  +---------+-----------+------------+
     *
     * In the end, the result is:
     *    alpha
     *      - india
     *    bravo
     *      - echo
     *    charlie
     *    delta
     *      - foxtrot
     */
    public function aliasInfo(): array {
        self::$db->prepared_query("
             SELECT AliasID as aliasId, Name as aliasName, UserID as userId,  Redirect as redirectId
             FROM artists_alias
             WHERE ArtistID = ?
             ORDER BY Redirect, Name
             ", $this->id
        );
        $result = self::$db->to_array('aliasId', MYSQLI_ASSOC, false);

        // go through the list and tie the alias to its non-redirecting ancestor
        $userMan = new Manager\User();
        $alias = [$this->primaryAliasId() => null];  // ensure primary alias is always the first item
        foreach ($result as $aliasId => $info) {
            if ($info['redirectId']) {
                $alias[$info['redirectId']]['alias'][] = [
                    'alias_id' => $aliasId,
                    'name'     => $info['aliasName'],
                    'user'     => $userMan->findById($info['userId']),
                ];
            } else {
                $alias[$aliasId] = [
                    'alias'    => [],
                    'alias_id' => $aliasId,
                    'name'     => $info['aliasName'],
                    'user'     => $userMan->findById($info['userId']),
                ];
            }
        }

        // the aliases may need to be sorted
        foreach ($alias as &$a) {
            if ($a['alias']) {
                uksort($a['alias'], fn ($x, $y) => strtolower($a['alias'][$x]['name']) <=> strtolower($a['alias'][$y]['name']));
            }
        }
        return $alias;
    }

    public function requestIdUsage(): array {
        self::$db->prepared_query("
            SELECT DISTINCT r.ID
            FROM requests AS r
            INNER JOIN requests_artists AS ra ON (ra.RequestID = r.ID)
            INNER JOIN artists_alias       aa ON (ra.AliasID = aa.AliasID)
            WHERE aa.ArtistID = ?
            ", $this->id
        );
        return self::$db->collect(0, false);
    }

    public function tgroupIdUsage(): array {
        self::$db->prepared_query("
            SELECT DISTINCT tg.ID
            FROM torrents_group AS tg
            INNER JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
            INNER JOIN artists_alias       aa ON (ta.AliasID = aa.AliasID)
            WHERE aa.ArtistID = ?
            ", $this->id
        );
        return self::$db->collect(0, false);
    }

    public function usageTotal(): int {
        return count($this->requestIdUsage()) + count($this->tgroupIdUsage());
    }

    /**
     * Modify an artist. If the body or image fields are edited, or any other
     * change that has to appear in the history, a revision is created.
     * Since a revision requires the user who made the edit to be recorded,
     * the user is passed in as another field to update.
     * The body, image, summary and updater  fields are then cleared so that
     * the BaseObject method can do its job.
     */

    public function modify(): bool {
        // handle the revision of body and image
        $revisionData = [];
        $summary      = [];
        if ($this->field('body') !== null) {
            $body = $this->clearField('body');
            if (is_string($body)) {
                $revisionData['body'] = $body;
                $summary[] = 'description changed (len=' . mb_strlen($body) . ')';
            }
        }
        if ($this->field('image') !== null) {
            $image = $this->clearField('image');
            if (is_string($image)) {
                $revisionData['image'] = $image;
                $summary[] = "image changed to '$image'";
            }
        }
        $notes = $this->clearField('summary');
        if (is_array($notes)) {
            $summary = array_merge($summary, $notes);
        }
        $updated = false;
        if ($revisionData || $summary) {
            $this->setField('RevisionID',
                $this->createRevision(
                    body:    $revisionData['body'] ?? $this->body(),
                    image:   $revisionData['image'] ?? $this->image(),
                    summary: $summary,
                    user:    $this->updateUser,
                )
            );
            $updated = true;
        }

        // handle Discogs
        $discogs = $this->clearField('discogs');
        if ($discogs) {
            $updated = true;
            if ($discogs->sequence() > 0) {
                $this->setDiscogsRelation($discogs);
            } else {
                $this->removeDiscogsRelation();
            }
        }

        $parentUpdated = parent::modify();
        $this->flush();
        return $parentUpdated || $updated;
    }

    /**
     * Sets the Discogs ID for the artist and returns the number of affected rows.
     */
    public function setDiscogsRelation(Util\Discogs $discogs): int {
        // We only run this query when artist_discogs_id has changed, so the collision
        // should only happen on the UNIQUE(artist_id) index
        self::$db->prepared_query("
            INSERT INTO artist_discogs
                   (artist_discogs_id, artist_id, is_preferred, sequence, stem, name, user_id)
            VALUES (?,                 ?,         ?,            ?,        ?,    ?,    ?)
            ON DUPLICATE KEY UPDATE
                artist_discogs_id = VALUES(artist_discogs_id),
                is_preferred      = VALUES(is_preferred),
                sequence          = VALUES(sequence),
                stem              = VALUES(stem),
                name              = VALUES(name),
                user_id           = VALUES(user_id)
            ", $discogs->id(), $this->id, (int)($this->homonymCount() == 0),
            $discogs->sequence(), $discogs->stem(), $discogs->name(), $this->updateUser->id()
        );
        return self::$db->affected_rows();
    }

    public function removeDiscogsRelation(): int {
        self::$db->prepared_query('
            DELETE FROM artist_discogs WHERE artist_id = ?
            ', $this->id
        );
        return self::$db->affected_rows();
    }

    public function merge(
        Artist          $old,
        bool            $redirect,
        User            $user,
        Manager\Collage $collMan,
        Manager\Comment $commMan,
        Manager\Request $reqMan,
        Manager\TGroup  $tgMan,
        Log             $logger,
    ): int {
        self::$db->begin_transaction();

        // Get the ids of the objects that need to be flushed
        $oldId = $old->id();
        $oldName = $old->name();
        self::$db->prepared_query("
            SELECT UserID FROM bookmarks_artists WHERE ArtistID = ?
            ", $oldId
        );
        $bookmarkList = self::$db->collect(0, false);
        self::$db->prepared_query("
            SELECT ca.CollageID
            FROM collages_artists AS ca
            WHERE ca.ArtistID = ?
            ", $oldId
        );
        $artistCollageList = self::$db->collect(0, false);
        self::$db->prepared_query("
            SELECT DISTINCT GroupID
            FROM torrents_artists ta
            INNER JOIN artists_alias aa ON (ta.AliasID = aa.AliasID)
            WHERE aa.ArtistID = ?
            ", $oldId
        );
        $groupList = self::$db->collect(0, false);
        self::$db->prepared_query("
            SELECT DISTINCT RequestID
            FROM requests_artists ra
            INNER JOIN artists_alias aa ON (ra.AliasID = aa.AliasID)
            WHERE aa.ArtistID = ?
            ", $oldId
        );
        $requestList = self::$db->collect(0, false);

        // only need to flush torrent collages, no db update is required
        self::$db->prepared_query("
            SELECT DISTINCT ct.CollageID
            FROM collages_torrents      ct
            INNER JOIN torrents_artists ta USING (GroupID)
            INNER JOIN artists_alias    aa ON (ta.AliasID = aa.AliasID)
            WHERE aa.ArtistID = ?
            ", $oldId
        );
        $collageList = self::$db->collect(0, false);

        // Update the old artist id to the new one in the target object,
        // if it does not yet exists there. Delete any remaining old ids
        // as the new id is already present in the target object.
        // In Postgresql this will be handled by a merge statement.
        $newId = $this->id();
        self::$db->prepared_query("
            UPDATE bookmarks_artists
            LEFT JOIN (SELECT UserID FROM bookmarks_artists WHERE ArtistID = ?) X USING (UserID)
            SET ArtistID = ?
            WHERE ArtistID = ? AND X.UserID IS NULL;
            ", $newId, $newId, $oldId
        );
        self::$db->prepared_query("
            DELETE FROM bookmarks_artists WHERE ArtistID = ?
            ", $oldId
        );
        self::$db->prepared_query("
            UPDATE collages_artists Old
            LEFT JOIN (SELECT CollageID from collages_artists where ArtistID = ?) New using (CollageID)
            SET Old.ArtistID = ?
            WHERE Old.ArtistID = ? AND New.CollageID IS NULL
            ", $newId, $newId, $oldId
        );
        self::$db->prepared_query("
            DELETE FROM collages_artists WHERE ArtistID = ?
            ", $oldId
        );

        // Merge all of this artist's aliases with the new artist
        self::$db->prepared_query("
            UPDATE artists_alias SET
                ArtistID = ?
            WHERE ArtistID = ?
            ", $newId, $oldId
        );

        if ($redirect) {
            // passing ArtistID because no index on Redirect
            self::$db->prepared_query("
                UPDATE artists_alias SET
                    Redirect = ?
                WHERE (AliasID = ? OR Redirect = ?) AND ArtistID = ?
                ", $this->primaryAliasId(), $old->primaryAliasId(), $old->primaryAliasId(), $newId
            );
            self::$db->prepared_query("
                UPDATE IGNORE torrents_artists SET AliasID = ?  WHERE AliasID = ?
            ", $this->primaryAliasId(), $old->primaryAliasId()
            );
            self::$db->prepared_query("
                UPDATE IGNORE requests_artists SET AliasID = ? WHERE AliasID = ?
            ", $this->primaryAliasId(), $old->primaryAliasId()
            );
            self::$db->prepared_query("
                DELETE FROM torrents_artists WHERE AliasID = ?
            ", $old->primaryAliasId()
            );
            self::$db->prepared_query("
                DELETE FROM requests_artists WHERE AliasID = ?
            ", $old->primaryAliasId()
            );
        }

        $commMan->merge('artist', $oldId, $newId);

        // Cache clearing
        self::$cache->delete_multi([array_map(fn ($id) => "notify_artists_$id", $bookmarkList)]);
        self::$cache->delete_multi([array_map(fn ($id) => sprintf(\Gazelle\Collage::CACHE_KEY, $id), $collageList)]);
        foreach ($artistCollageList as $collageId) {
            $collMan->findById($collageId)?->flush();
        }
        foreach ($groupList as $tgroupId) {
            $tgMan->findById($tgroupId)?->refresh();
        }
        foreach ($requestList as $requestId) {
            $reqMan->findById($requestId)?->updateSphinx();
        }

        // Delete the old artist
        self::$db->prepared_query("
            DELETE FROM artists_group WHERE ArtistID = ?
            ", $oldId
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();
        $logger->general(
            "The artist $oldId ($oldName) was made into a " . ($redirect ? "" : "non-") . "redirecting alias of artist $newId ({$this->name()}) by user {$user->label()}"
        );
        self::$cache->delete_value("zz_a_$oldId");
        $this->flush();
        $old->flush();
        return $affected;
    }

    /**
     * rename an alias
     */
    public function renameAlias(int $aliasId, string $newName, User $user, Manager\Request $reqMan, Manager\TGroup $tgMan): ?int {
        $alias = $this->aliasList()[$aliasId];

        if ($alias['redirect_id']) {
            // renaming a redirect is not supported, should be deleted
            return null;
        }

        $oldName = $alias['name'];
        if ($oldName === $newName) {
            return null;
        }

        [$newId, $newArtistId, $newRedirect] = self::$db->row("
            SELECT AliasID, ArtistID, Redirect
            FROM artists_alias
            WHERE name = ?
            ", $newName
        );

        if ($newArtistId && $newArtistId !== $this->id) {
            // new name already set for different artist, don't do anything
            return null;
        }

        self::$db->begin_transaction();
        if (strcasecmp($oldName, $newName) === 0 || $aliasId === $newId) {
            // case-correction or diacritics change
            self::$db->prepared_query("
                UPDATE artists_alias SET
                  Name = ?
                WHERE AliasID = ?
                ", $newName, $aliasId
            );
            $newId = $aliasId;
        } elseif ($newId) {
            // change this alias to a redirect to an existing NRA
            if ($newRedirect) {
                // target alias has a redirect, clean up aliases first
                return null;
            }
            self::$db->prepared_query("
                UPDATE artists_alias SET
                  Redirect = ?
                WHERE AliasID = ?
                ", $newId, $aliasId
            );
            // note: pass ArtistID because there is no index on Redirect
            self::$db->prepared_query("
                UPDATE artists_alias SET
                  Redirect = ?
                WHERE Redirect = ? AND ArtistID = ?
                ", $newId, $aliasId, $this->id
            );
        } else {
            // create a new alias and degrade the old one into a redirect
            self::$db->prepared_query("
                INSERT INTO artists_alias
                       (ArtistID, Name, UserID, Redirect)
                VALUES (?,        ?,    ?,      0)
                ", $this->id, $newName, $user->id()
            );
            $newId = self::$db->inserted_id();
            // note: pass ArtistID because there is no index on Redirect
            self::$db->prepared_query("
                UPDATE artists_alias SET
                  Redirect = ?
                WHERE (Redirect = ? OR AliasID = ?) AND ArtistID = ?
                ", $newId, $aliasId, $aliasId, $this->id
            );
        }

        if ($aliasId !== $newId && $aliasId === $this->primaryAliasId()) {
            self::$db->prepared_query("
                    UPDATE artists_group SET PrimaryAlias = ? WHERE ArtistID = ?
                    ", $newId, $this->id
            );
        }

        // process artists in torrents
        self::$db->prepared_query("
            SELECT GroupID FROM torrents_artists WHERE AliasID = ?
            ", $aliasId
        );
        $groups = self::$db->collect('GroupID');
        self::$db->prepared_query("
            UPDATE IGNORE torrents_artists SET AliasID = ?  WHERE AliasID = ?
            ", $newId, $aliasId
        );
        foreach ($groups as $groupId) {
            $tgMan->findById($groupId)?->refresh();
        }

        // process artists in requests
        self::$db->prepared_query("
            SELECT RequestID FROM requests_artists WHERE AliasID = ?
            ", $aliasId
        );
        $requests = self::$db->collect('RequestID');
        self::$db->prepared_query("
            UPDATE IGNORE requests_artists SET AliasID = ? WHERE AliasID = ?
            ", $newId, $aliasId
        );

        if ($aliasId !== $newId) {
            // delete entries that exist for both old + new alias
            self::$db->prepared_query("
                DELETE FROM torrents_artists WHERE AliasID = ?
                ", $aliasId
            );
            self::$db->prepared_query("
                DELETE FROM requests_artists WHERE AliasID = ?
                ", $aliasId
            );
        }

        self::$db->commit();

        foreach ($requests as $requestId) {
            $reqMan->findById($requestId)->updateSphinx();
        }
        $this->flush();
        return $newId;
    }

    /**
     * Deletes an artist and their wiki and tags.
     * Does NOT delete their requests or torrents.
     */
    public function remove(User $user, Log $logger): int {
        $qid  = self::$db->get_query_id();
        $id   = $this->id;
        $name = $this->name();
        $this->flush();
        $db = new \Gazelle\DB();

        self::$db->begin_transaction();
        $db->relaxConstraints(true);
        self::$db->prepared_query("DELETE FROM artist_has_attr WHERE artist_id = ?", $id);
        self::$db->prepared_query("DELETE FROM artists_alias WHERE ArtistID = ?", $id);
        self::$db->prepared_query("DELETE FROM artists_group WHERE ArtistID = ?", $id);
        self::$db->prepared_query("DELETE FROM artists_tags WHERE ArtistID = ?", $id);
        self::$db->prepared_query("DELETE FROM wiki_artists WHERE PageID = ?", $id);
        $db->relaxConstraints(false);

        (new \Gazelle\Manager\Comment())->remove('artist', $id);
        $logger->general("Artist $id ($name) was deleted by " . $user->username());
        self::$db->commit();

        self::$cache->delete_value('zz_a_' . $id);
        self::$cache->decrement('stats_artist_count');

        self::$db->set_query_id($qid);
        return 1;
    }

    /* STATIC METHODS - for when you do not yet have an ID, e.g. during creation */
    /**
     * Collapse whitespace and directional markers, because people copypaste carelessly.
     * TODO: make stricter, e.g. on all whitespace characters or Unicode normalisation
     */
    public static function sanitize(string $name): ?string {
        // \u200e is &lrm;
        $name = preg_replace('/^(?:\xE2\x80\x8E|\s)+/', '', $name);
        $name = preg_replace('/(?:\xE2\x80\x8E|\s)+$/', '', $name);
        return preg_replace('/ +/', ' ', $name);
    }
}
