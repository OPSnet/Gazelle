<?php

namespace Gazelle;

class TGroup extends BaseObject {
    final const CACHE_KEY            = 'tg_%d';
    final const CACHE_TLIST_KEY      = 'tlist_%d';
    final const CACHE_COVERART_KEY   = 'tg_cover_%d';
    final const USER_RECENT_SNATCH   = 'u_recent_snatch_%d';
    final const USER_RECENT_UPLOAD   = 'u_recent_up_%d';
    final const CACHE_REQUEST_TGROUP = 'req_tg_%d';

    final const ARTIST_DISPLAY_TEXT = 1;
    final const ARTIST_DISPLAY_HTML = 2;

    protected int   $revisionId = 0;
    protected bool  $showFallbackImage = true;
    protected ArtistRole\TGroup $artistRole;
    protected User              $viewer;
    protected Stats\TGroup      $stats;

    public function flush(): TGroup {
        $this->info = [];
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->id),
            sprintf(self::CACHE_TLIST_KEY, $this->id),
            sprintf(self::CACHE_COVERART_KEY, $this->id),
            "torrent_group_{$this->id}",
            "groups_artists_{$this->id}",
        ]);
        return $this;
    }

    public function link(): string {
        $url = "<a href=\"{$this->url()}\" title=\"" . ($this->hashTag() ?: 'View torrent group') . '" dir="ltr">'
            . display_str($this->name()) . '</a>';
        return match($this->categoryName()) {
            'Music'  => "{$this->artistRole()->link()} – $url [{$this->year()} {$this->releaseTypeName()}]",
            'Audiobooks',
            'Comedy' => "$url [{$this->year()}]",
            default  => $url,
        };
    }

    public function location(): string { return "torrents.php?id={$this->id}"; }
    public function tableName(): string { return 'torrents_group'; }

    public function torrentLink(int $torrentId): string {
        $url = '<a href="' . $this->url() . "&amp;torrentid={$torrentId}#torrent{$torrentId}\" dir=\"ltr\">"
            . display_str($this->name()) . '</a>';
        return match($this->categoryName()) {
            'Music'  => "{$this->artistRole()->link()} – $url [{$this->year()} {$this->releaseTypeName()}]",
            'Audiobooks',
            'Comedy' => "$url [{$this->year()}]",
            default  => $url,
        };
    }

    public function text(): string {
        $name = display_str($this->name());
        return match($this->categoryName()) {
            'Music'  => "{$this->artistRole()->text()} – $name [{$this->year()} {$this->releaseTypeName()}]"
                . ($this->isShowcase() ? '[Showcase]' : ''),
            'Audiobooks',
            'Comedy' => "$name [{$this->year()}]",
            default  => $name,
        };
    }

    /**
     * When the image of a release group is changed, we need to flush other things
     */
    public function imageFlush(): TGroup {
        self::$db->prepared_query("
            SELECT CollageID FROM collages_torrents WHERE GroupID = ?
            ", $this->id
        );
        if (self::$db->has_results()) {
            self::$cache->delete_multi(array_map(fn ($id) => "collagev2_$id", self::$db->collect(0, false)));
        }

        self::$db->prepared_query("
            SELECT DISTINCT UserID
            FROM torrents AS t
            LEFT JOIN torrents_group AS tg ON (t.GroupID = tg.ID)
            WHERE tg.ID = ?
            ", $this->id
        );
        if (self::$db->has_results()) {
            self::$cache->delete_multi(array_map(fn ($id) => sprintf(self::USER_RECENT_UPLOAD, $id), self::$db->collect(0, false)));
        }

        self::$db->prepared_query("
            SELECT DISTINCT xs.uid
            FROM xbt_snatched xs
            INNER JOIN torrents t ON (t.ID = xs.fid)
            WHERE t.GroupID = ?
            ", $this->id
        );
        if (self::$db->has_results()) {
            self::$cache->delete_multi(array_map(fn ($id) => sprintf(self::USER_RECENT_SNATCH, $id), self::$db->collect(0, false)));
        }
        return $this;
    }

    public function touch(): TGroup {
        self::$db->prepared_query('
            UPDATE torrents_group SET
                Time = now()
            WHERE ID = ?
            ', $this->id
        );
        return $this;
    }

    public function setViewer(User $viewer): TGroup {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * Toggle whether an internal URL is returnd for missing cover artwork
     * is returned, or null. Used by API endpoints.
     */
    public function showFallbackImage(bool $showFallbackImage): TGroup {
        $this->showFallbackImage = $showFallbackImage;
        return $this;
    }

    public function revisionList(): array {
         self::$db->prepared_query("
            SELECT RevisionID AS revision,
                Summary       AS summary,
                Time          AS time,
                UserID        AS user_id
            FROM wiki_torrents
            WHERE PageID = ?
            ORDER BY RevisionID DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the metadata of the torrent
     *
     * @return array of many things
     */
    public function info(int $revisionId = 0): ?array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $this->revisionId = $revisionId;
        if (!$revisionId) {
            $cached = self::$cache->get_value($key);
            if (is_array($cached)) {
                $refresh = false;
                if (!isset($cached['Image'])) {
                    $cached['Image'] = $this->showFallbackImage
                        ? (STATIC_SERVER . '/common/noartwork/' . CATEGORY_ICON[$cached['CategoryID'] - 1])
                        : null;
                    $refresh = true;
                }
                $cached['Flags'] = [
                    'IsSnatched' => $this->fetchIsSnatched(),
                ];
                if (!isset($cached['torrent_list'])) {
                    self::$db->prepared_query("
                        SELECT t.ID
                        FROM torrents t
                        WHERE t.GroupID = ?
                        ORDER BY t.Remastered, (t.RemasterYear != 0) DESC, t.RemasterYear, t.RemasterTitle,
                            t.RemasterRecordLabel, t.RemasterCatalogueNumber, t.Media, t.Format, t.Encoding, t.ID
                        ", $this->id
                    );
                    $cached['torrent_list'] = self::$db->collect(0, false);
                    $refresh = true;
                }
                if ($refresh) {
                    self::$cache->cache_value($key, $cached, 0);
                }
                $this->info = $cached;
                $this->info['from_cache'] = true;
                return $this->info;
            }
        }
        $sql = 'SELECT '
            . ($this->revisionId ? 'w.Body, w.Image,' : 'tg.WikiBody AS Body, tg.WikiImage AS Image,')
            . " tg.ID,
                tg.Name,
                tg.Year,
                tg.RecordLabel,
                tg.CatalogueNumber,
                tg.ReleaseType,
                tg.CategoryID,
                tg.Time,
                tg.VanityHouse,
                group_concat(tag.Name ORDER BY tt.PositiveVotes - tt.NegativeVotes DESC, tag.Name)         AS tagNames,
                group_concat(tag.ID ORDER BY tt.PositiveVotes - tt.NegativeVotes DESC, tag.Name)           AS tagIds,
                group_concat(tag.UserID ORDER BY tt.PositiveVotes - tt.NegativeVotes DESC, tag.Name)       AS tagVoteUserIds,
                group_concat(tt.PositiveVotes ORDER BY tt.PositiveVotes - tt.NegativeVotes DESC, tag.Name) AS tagUpvotes,
                group_concat(tt.NegativeVotes ORDER BY tt.PositiveVotes - tt.NegativeVotes DESC, tag.Name) AS tagDownvotes,
                (tgha.TorrentGroupID IS NOT NULL) AS noCoverArt,
                max(coalesce(t.Size, 0)) AS max_torrent_size,
                max(coalesce(t.Time, '2000-01-01 00:00:00')) AS most_recent_upload
            FROM torrents_group AS tg
            LEFT JOIN torrents AS t ON (t.GroupID = tg.ID)
            LEFT JOIN torrents_tags AS tt ON (tt.GroupID = tg.ID)
            LEFT JOIN tags as tag ON (tag.ID = tt.TagID)
            LEFT JOIN torrent_group_has_attr AS tgha ON (tgha.TorrentGroupID = tg.ID
                AND tgha.TorrentGroupAttrID = (SELECT tga.ID FROM torrent_group_attr tga WHERE tga.Name = 'no-cover-art')
            )
        ";

        $args = [];
        if ($this->revisionId) {
            $sql .= '
                LEFT JOIN wiki_torrents AS w ON (w.PageID = ? AND w.RevisionID = ?)';
            $args[] = $this->id;
            $args[] = $this->revisionId;
        }
        $sql .= " WHERE tg.ID = ? GROUP BY tg.ID";
        $args[] = $this->id;

        $info = self::$db->rowAssoc($sql, ...$args);

        // make the values sane (null, boolean as appropriate)
        // TODO: once all get_*_info calls have been ported over, do this prior to caching
        foreach (['CatalogueNumber', 'RecordLabel'] as $nullable) {
            $info[$nullable] = $info[$nullable] == '' ? null : $info[$nullable];
        }
        $info['VanityHouse'] = ($info['VanityHouse'] == 1);
        $info['ReleaseType'] = (int)$info['ReleaseType'];

        // Reorganize tag info to be useful
        $tagNames       = explode(',', $info['tagNames'] ?? '');
        $tagIds         = array_map('intval', explode(',', $info['tagIds'] ?? ''));
        $tagVoteUserIds = array_map('intval', explode(',', $info['tagVoteUserIds'] ?? ''));
        $tagUpvotes     = array_map('intval', explode(',', $info['tagUpvotes'] ?? ''));
        $tagDownvotes   = array_map('intval', explode(',', $info['tagDownvotes'] ?? ''));
        $info['tags']   = [];
        for ($n = 0; $n < count($tagIds); ++$n) {
            $info['tags'][$tagIds[$n]] = [
                'name'      => $tagNames[$n],
                'id'        => $tagIds[$n],
                'userId'    => $tagVoteUserIds[$n],
                'upvotes'   => $tagUpvotes[$n],
                'downvotes' => $tagDownvotes[$n],
                'score'     => $tagUpvotes[$n] - $tagDownvotes[$n],
            ];
        }

        self::$db->prepared_query("
            SELECT t.ID
            FROM torrents t
            WHERE t.GroupID = ?
            ORDER BY t.Remastered, (t.RemasterYear != 0) DESC, t.RemasterYear, t.RemasterTitle,
                t.RemasterRecordLabel, t.RemasterCatalogueNumber, t.Media, t.Format, t.Encoding, t.ID
            ", $this->id
        );
        $info['torrent_list'] = self::$db->collect(0, false);

        if (!$this->revisionId) {
            self::$cache->cache_value($key, $info, 0);
        }
        if (!$info['Image']) {
            $info['Image'] = $this->showFallbackImage
                ? (STATIC_SERVER . '/common/noartwork/' . CATEGORY_ICON[$info['CategoryID'] - 1])
                : null;
        }
        $info['Flags'] = [
            'IsSnatched' => $this->fetchIsSnatched(),
        ];
        $info['from_cache'] = false;
        $this->info = $info;
        return $this->info;
    }

    protected function fetchIsSnatched(): bool {
        return isset($this->viewer) && $this->viewer->option('ShowSnatched') && (bool)self::$db->scalar("
            SELECT 1
            FROM torrents_group tg
            WHERE exists(
                SELECT 1
                FROM torrents t
                INNER JOIN xbt_snatched xs ON (xs.fid = t.ID)
                WHERE t.GroupID = tg.ID
                    AND xs.uid = ?
                )
                AND tg.ID = ?
            ", $this->viewer->id(), $this->id
        );
    }

    public function artistName(): string {
        return $this->artistRole()->text();
    }

    public function artistRole(): ?ArtistRole\TGroup {
        if ($this->categoryName() !== 'Music') {
            return null;
        }
        if (!isset($this->artistRole)) {
            $this->artistRole = new ArtistRole\TGroup($this->id, new Manager\Artist);
        }
        return $this->artistRole;
    }

    public function catalogueNumber(): ?string {
        return $this->info()['CatalogueNumber'];
    }

    public function categoryId(): int {
        return $this->info()['CategoryID'];
    }

    public function categoryGrouped(): bool {
        return isset(CATEGORY_GROUPED[$this->categoryId() - 1]);
    }

    public function categoryName(): string {
        return CATEGORY[$this->categoryId() - 1];
    }

    public function categoryCss(): string {
        return 'cats_' . strtolower(str_replace(['-', ' '], '', $this->categoryName()));
    }

    public function cover(): string {
        return $this->info()['Image']
            ?? (STATIC_SERVER . '/common/noartwork/' . strtolower($this->categoryName()) . ".png");
    }

    public function description(): string {
        return $this->info()['Body'] ?? '';
    }

    public function hasNoCoverArt(): bool {
        return $this->info()['noCoverArt'] ?? false;
    }

    public function image(): ?string {
        return $this->info()['Image'];
    }

    public function isOwner(int $userId): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM torrents t
            WHERE t.GroupID = ?
                AND t.UserID = ?
            ", $this->id, $userId
        );
    }

    public function isShowcase(): bool {
        return $this->info()['VanityHouse'];
    }

    public function isSnatched(): bool {
        return $this->info()['Flags']['IsSnatched'];
    }

    public function label(): string {
        return $this->id . " (" . $this->name() . ")";
    }

    public function maxTorrentSize(): int {
        return $this->info()['max_torrent_size'];
    }

    public function mostRecentUpload(): string {
        return $this->info()['most_recent_upload'];
    }

    public function name(): string {
        return $this->info()['Name'];
    }

    public function recordLabel(): ?string {
        return $this->info()['RecordLabel'];
    }

    /**
     * @return int Release type (will be null for non-Music categories)
     */
    public function releaseType(): ?int {
        return $this->info()['ReleaseType'] == 0 ? null : $this->info()['ReleaseType'];
    }

    public function releaseTypeName(): ?string {
        static $releaseTypes;
        if (is_null($releaseTypes)) {
            $releaseTypes = (new \Gazelle\ReleaseType)->list();
        }
        return $this->info()['ReleaseType'] == 0 ? null : $releaseTypes[$this->releaseType()];
    }

    public function remasterList(): array {
        self::$db->prepared_query("
            SELECT min(ID)               AS id,
                RemasterYear             AS year,
                RemasterTitle            AS title,
                RemasterRecordLabel      AS record_label,
                RemasterCatalogueNumber  AS catalogue_number
            FROM torrents
            WHERE Remastered = '1'
                AND RemasterYear != 0
                AND GroupID = ?
            GROUP BY RemasterYear,
                RemasterTitle,
                RemasterRecordLabel,
                RemasterCatalogueNumber
            ORDER BY RemasterYear DESC,
                RemasterTitle DESC,
                RemasterRecordLabel DESC,
                RemasterCatalogueNumber DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_BOTH, false);
    }

    /**
     * Delegate stats methods to the Stats\TGroup class
     */
    public function stats(): \Gazelle\Stats\TGroup {
        if (!isset($this->stats)) {
            $this->stats = new Stats\TGroup($this->id);
        }
        return $this->stats;
    }

    public function tagList(): array {
        return $this->info()['tags'];
    }

    public function primaryTag(): string {
        $tagList = $this->tagList();
        return $tagList ? ucfirst(current($tagList)['name']) : '';
    }

    public function primaryTagCss(): string {
        return 'tags_' .  str_replace('.', '_', $this->primaryTag());
    }

    public function tagNameList(): array {
        return array_map(fn($t) => $t['name'], $this->tagList());
    }

    public function hashTag(): string {
        return implode(' ', array_map(fn($t) => htmlentities("#$t"), $this->tagNameList()));
    }

    public function time(): string {
        return $this->info()['Time'];
    }

    public function year(): ?int {
        return $this->info()['Year'];
    }

    public function torrentTagList(): array {
        $tag = [];
        foreach ($this->info()['tags'] as $t) {
            $tag[] = "<a href=\"torrents.php?taglist={$t['name']}\">{$t['name']}</a>";
        }
        return $tag;
    }

    public function torrentIdList(): array {
        return $this->info()['torrent_list'];
    }

    /**
     * Is the user allowed to edit this group? They can if they have the appropriate privilege,
     * or, for User or Members etc, if they own an upload in the group (they have a stake in the game) if the user is in
     * a entry-level userclass.
     */
    public function canEdit(User $user): bool {
        return $user->permitted('torrents_edit')
            || (bool)self::$db->scalar("
                    SELECT 1 FROM torrents WHERE GroupID = ? AND UserID = ? LIMIT 1
                    ", $this->id, $user->id()
                );
    }

    public function addCoverArt(string $image, string $summary, int $userId, \Gazelle\Log $logger): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO cover_art
                   (GroupID, Image, Summary, UserID)
            VALUES (?,       ?,     ?,       ?)
            ", $this->id, $image, $summary, $userId
        );
        $id = self::$db->inserted_id();
        if ($id) {
            $logger->group($this->id, $userId, "Additional cover \"$summary - $image\" added to group");
            self::$cache->delete_value(sprintf(self::CACHE_COVERART_KEY, $this->id));
        }
        return $id;
    }

    public function removeCoverArt(int $coverId, int $userId, \Gazelle\Log $logger): int {
        [$image, $summary] = self::$db->row("
            SELECT Image, Summary
            FROM cover_art
            WHERE ID = ?
            ", $coverId
        );
        if (is_null($image)) {
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM cover_art WHERE ID = ?
            ", $coverId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $logger->group($this->id, $userId, "Additional cover \"$summary - $image\" removed from group");
            self::$cache->delete_value(sprintf(self::CACHE_COVERART_KEY, $this->id));
        }
        return $affected;
    }

    public function coverArt(Manager\User $userMan): array {
        $key = sprintf(self::CACHE_COVERART_KEY, $this->id);
        $list = self::$cache->get_value($key);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT ID, Image, Summary, UserID, Time
                FROM cover_art
                WHERE GroupID = ?
                ORDER BY Time ASC
                ", $this->id
            );
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $list, 0);
        }
        foreach ($list as &$cover) {
            $user = $userMan->findById($cover['UserID']);
            $cover['userlink'] = $user ? $user->link() : 'System';
        }
        return $list;
    }

    public function toggleNoCoverArt(bool $noCoverArt): int {
        if ($noCoverArt) {
            self::$db->prepared_query("
                INSERT INTO torrent_group_has_attr
                   (TorrentGroupID, TorrentGroupAttrID)
                VALUES (?, (SELECT ID FROM torrent_group_attr WHERE Name = 'no-cover-art'))
                ", $this->id
            );
        } else {
            self::$db->prepared_query("
                DELETE FROM torrent_group_has_attr
                WHERE TorrentGroupAttrID = (SELECT ID FROM torrent_group_attr WHERE Name = 'no-cover-art')
                    AND TorrentGroupID = ?
                ", $this->id
            );
        }
        return self::$db->affected_rows();
    }

    public function primaryArtist(): ?Artist {
        $roleList = $this->artistRole()->roleList();
        foreach (['dj', 'composer', 'conductor', 'main'] as $role) {
            if (count($roleList[$role])) {
                return new Artist($roleList[$role][0]['id']);
            }
        }
        return null;
    }

    /**
     * Add artists to a group. The role and name arrays must be the same length, and
     * are walked down in step, to match the artist with their role in the group
     */
    public function addArtists(array $roles, array $names, User $user, Manager\Artist $artistMan, Log $logger): int {
        $userId = $user->id();
        $add = [];
        $args = [];
        $seen = [];
        $n = count($names);
        for ($i = 0; $i < $n; $i++) {
            $role = $roles[$i];
            $name = \Gazelle\Artist::sanitize($names[$i]);
            if (!$name || !in_array($role, array_keys(ARTIST_TYPE))) {
                continue;
            }
            [$artistId, $aliasId] = $artistMan->fetchArtistIdAndAliasId($name);
            if ($artistId && !isset($seen["$role:$artistId"])) {
                $seen["$role:$artistId"] = true;
                array_push($args, $this->id, $userId, $artistId, $aliasId, $role, (string)$role);
                $add[] = "$artistId ($name) as " . ARTIST_TYPE[$role];
            }
        }
        if (empty($add)) {
            return 0;
        }
        self::$db->prepared_query("
            INSERT IGNORE INTO torrents_artists
                   (GroupID, UserID, ArtistID, AliasID, artist_role_id, Importance)
            VALUES " . placeholders($add, '(?, ?, ?, ?, ?, ?)')
            , ...$args
        );

        foreach ($add as $artistLabel) {
            $logger->group($this->id, $user->id(), "Added artist $artistLabel")
                ->general("Artist $artistLabel was added to the group {$this->label()} by user {$user->label()}");
        }
        self::$cache->increment_value('stats_album_count', count($names));
        $this->refresh();
        return count($add);
    }

    public function removeArtist(Artist $artist, int $role, User $user, Log $logger): bool {
        self::$db->prepared_query('
            DELETE FROM torrents_artists
            WHERE GroupID = ?
                AND ArtistID = ?
                AND Importance = ?
            ', $this->id, $artist->id(), $role
        );
        if (!self::$db->affected_rows()) {
            return false;
        }
        if ($artist->usageTotal() === 0) {
            $artist->remove($user, $logger);
        }
        $this->flush();
        return true;
    }

    public function rawTorrentList(): array {
        $key = sprintf(self::CACHE_TLIST_KEY, $this->id);
        if (!$this->revisionId) {
            $list = self::$cache->get_value($key);
            if ($list !== false) {
                return $list;
            }
        }

        $columns = "
                t.ID,
                t.Media,
                t.Format,
                t.Encoding,
                t.Remastered,
                t.RemasterYear,
                t.RemasterTitle,
                t.RemasterRecordLabel,
                t.RemasterCatalogueNumber,
                t.Scene,
                t.HasLog,
                t.HasCue,
                t.HasLogDB,
                t.LogScore,
                t.LogChecksum,
                t.FileCount,
                t.Size,
                tls.Seeders,
                tls.Leechers,
                tls.Snatched,
                t.FreeTorrent,
                t.Time,
                t.Description,
                t.FileList,
                t.FilePath,
                t.UserID,
                tls.last_action,
                HEX(t.info_hash) AS InfoHash,
                tbt.TorrentID AS BadTags,
                tbf.TorrentID AS BadFolders,
                tfi.TorrentID AS BadFiles,
                ml.TorrentID AS MissingLineage,
                ca.TorrentID AS CassetteApproved,
                lma.TorrentID AS LossymasterApproved,
                lwa.TorrentID AS LossywebApproved,
                t.LastReseedRequest,
                group_concat(tl.LogID) as ripLogIds
        ";

        self::$db->prepared_query("
            SELECT $columns, 0 as is_deleted
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
            LEFT JOIN torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
            LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
            LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
            WHERE t.GroupID = ?
            GROUP BY t.ID
            UNION DISTINCT
            SELECT $columns, 1 as is_deleted
            FROM deleted_torrents AS t
            INNER JOIN deleted_torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_tags AS tbt ON (tbt.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_folders AS tbf ON (tbf.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_bad_files AS tfi ON (tfi.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
            LEFT JOIN deleted_torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.ID)
            LEFT JOIN torrents_logs AS tl ON (tl.TorrentID = t.ID)
            WHERE t.GroupID = ?
            GROUP BY t.ID
            ORDER BY Remastered ASC,
                (RemasterYear != 0) DESC,
                RemasterYear ASC,
                RemasterTitle ASC,
                RemasterRecordLabel ASC,
                RemasterCatalogueNumber ASC,
                Media ASC,
                Format,
                Encoding,
                ID
            ", $this->id, $this->id
        );
        $list = self::$db->to_array('ID', MYSQLI_ASSOC, false);
        if (empty($list)) {
            return [];
        }
        if (!$this->revisionId) {
            self::$cache->cache_value($key, $list, in_array(0, self::$db->collect('Seeders')) ? 600 : 3600);
        }
        return $list;
    }

    /**
     * How many unresolved torrent reports are there in this group?
     */
    public function unresolvedReportsTotal(): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.GroupID = ?
            ", $this->id
        );
    }

    /**
     * Update the cache and sphinx delta index to keep everything up-to-date.
     */
    public function refresh(): TGroup {
        $qid = self::$db->get_query_id();

        $voteScore = (int)self::$db->scalar("
            SELECT Score FROM torrents_votes WHERE GroupID = ?
            ", $this->id
        );

        $artistName = (string)self::$db->scalar("
            SELECT group_concat(aa.Name separator ' ')
            FROM torrents_artists AS ta
            INNER JOIN artists_alias AS aa USING (AliasID)
            WHERE ta.Importance IN ('1', '4', '5', '6')
                AND ta.GroupID = ?
            GROUP BY ta.GroupID
            ", $this->id
        );

        self::$db->begin_transaction();
        // todo: remove this legacy code once TagList replacement is confirmed working
        $hasTags = (bool)self::$db->scalar("
            SELECT 1 FROM torrents_tags tt WHERE tt.GroupID = ? LIMIT 1
            ", $this->id
        );
        if ($hasTags) {
            self::$db->prepared_query("
                UPDATE torrents_group SET
                    TagList = (
                        SELECT REPLACE(GROUP_CONCAT(tags.Name SEPARATOR ' '), '.', '_')
                        FROM torrents_tags AS t
                        INNER JOIN tags ON (tags.ID = t.TagID)
                        WHERE t.GroupID = ?
                        GROUP BY t.GroupID
                    )
                WHERE ID = ?
                ", $this->id, $this->id
            );
        }

        self::$db->prepared_query("
            REPLACE INTO sphinx_delta
                (ID, GroupID, GroupName, Year, CategoryID, Time, ReleaseType, RecordLabel,
                CatalogueNumber, VanityHouse, Size, Snatched, Seeders, Leechers, LogScore, Scene, HasLog,
                HasCue, FreeTorrent, Media, Format, Encoding, Description, RemasterYear, RemasterTitle,
                RemasterRecordLabel, RemasterCatalogueNumber, FileList, TagList, VoteScore, ArtistName)
            SELECT
                t.ID, g.ID, g.Name, g.Year, g.CategoryID, t.Time, g.ReleaseType,
                g.RecordLabel, g.CatalogueNumber, g.VanityHouse, t.Size, tls.Snatched, tls.Seeders,
                tls.Leechers, t.LogScore, cast(t.Scene AS CHAR), cast(t.HasLog AS CHAR), cast(t.HasCue AS CHAR),
                cast(t.FreeTorrent AS CHAR), t.Media, t.Format, t.Encoding, t.Description,
                coalesce(t.RemasterYear, 0), t.RemasterTitle, t.RemasterRecordLabel, t.RemasterCatalogueNumber,
                replace(replace(t.FileList, '_', ' '), '/', ' ') AS FileList,
                replace(group_concat(coalesce(t2.Name, '') SEPARATOR ' '), '.', '_'), ?, ?
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            INNER JOIN torrents_group g ON (g.ID = t.GroupID)
            LEFT JOIN torrents_tags tt ON (tt.GroupID = g.ID)
            LEFT JOIN tags t2 ON (t2.ID = tt.TagID)
            WHERE g.ID = ?
            GROUP BY t.ID
            ", $voteScore, shortenString($artistName, 2048, false, false), $this->id
        );
        self::$db->commit();

        $this->flush();
        foreach (($this->artistRole()?->idList() ?? []) as $role) {
            foreach ($role as $artist) {
                self::$cache->delete_value('artist_groups_' . $artist['id']); //Needed for at least freeleech change, if not others.
            }
        }
        self::$db->set_query_id($qid);
        return $this;
    }

    public function addTagVote(int $userId, int $tagId, string $way): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT TagID
            FROM torrents_tags_votes
            WHERE GroupID = ?
                AND TagID = ?
                AND UserID = ?
                AND Way = ?
            ", $this->id, $tagId, $userId, $way
        );
        if (self::$db->has_results()) {
            self::$db->rollback();
            return 0;
        }
        if ($way == 'down') {
            $change = 'NegativeVotes = NegativeVotes + 1';
        } else {
            $change = 'PositiveVotes = PositiveVotes + 2';
        }
        self::$db->prepared_query("
            UPDATE torrents_tags SET
                $change
            WHERE GroupID = ?
                AND TagID = ?
            ", $this->id, $tagId
        );
        self::$db->prepared_query("
            INSERT INTO torrents_tags_votes
                   (GroupID, TagID, UserID, Way)
            VALUES (?,       ?,     ?,      ?)
            ", $this->id, $tagId, $userId, $way
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();
        $this->flush();
        return $affected;
    }

    public function removeTag(\Gazelle\Tag $tag): bool {
        $tagId = $tag->id();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM torrents_tags_votes WHERE GroupID = ? AND TagID = ?
            ", $this->id, $tagId
        );
        self::$db->prepared_query("
            DELETE FROM torrents_tags WHERE GroupID = ? AND TagID = ?
            ", $this->id, $tagId
        );
        if (!self::$db->affected_rows()) {
            return false;
        }

        // Was this the last occurrence?
        $inUse = self::$db->scalar("
            SELECT count(*) FROM torrents_tags WHERE TagID = ?
            ", $tagId
        ) + self::$db->scalar("
            SELECT count(*)
            FROM requests_tags rt
            INNER JOIN requests r ON (r.ID = rt.RequestID)
            WHERE r.FillerID = 0 /* TODO: change to DEFAULT NULL */
                AND rt.TagID = ?
            ", $tagId
        );
        if (!$inUse) {
            self::$db->prepared_query("
                DELETE FROM tags WHERE ID = ?
                ", $tagId
            );
        }

        self::$db->commit();
        return true;
    }

    /**
     * Insert a new revision (description or image has changed).
     *
     * return int revision id
     */
    public function createRevision(string $description, ?string $image, string $summary, User $user): int {
        self::$db->prepared_query("
            INSERT INTO wiki_torrents
                   (PageID, Body, Image, UserID, Summary)
            VALUES (?,      ?,    ?,     ?,      ?)
            ", $this->id, $description, $image, $user->id(), mb_substr(trim($summary), 0, 100)
        );
        $revisionId = self::$db->inserted_id();
        self::$db->prepared_query("
            UPDATE torrents_group SET
                RevisionID = ?
            WHERE ID = ?
            ", $revisionId, $this->id
        );
        $this->refresh();
        return $revisionId;
    }

    public function revertRevision(int $userId, int $revisionId): ?array {
        $revert = self::$db->row("
            SELECT Body, Image
            FROM wiki_torrents
            WHERE PageID = ? AND RevisionID = ?
            ", $this->id, $revisionId
        );
        if (is_null($revert)) {
            return null;
        }
        self::$db->prepared_query("
            INSERT INTO wiki_torrents
                   (PageID, Body, Image, UserID, Summary)
            SELECT  ?,      Body, Image, ?,      ?
            FROM wiki_torrents
            WHERE RevisionID = ?
            ", $this->id, $userId, "Reverted to revision $revisionId",
                $revisionId
        );
        return $revert;
    }

    public function remove(User $user): bool {
        // Artists
        // Collect the artist IDs and then wipe the torrents_artist entry
        self::$db->prepared_query("
            SELECT ArtistID FROM torrents_artists WHERE GroupID = ?
            ", $this->id
        );
        $artistList = self::$db->collect(0, false);
        self::$db->prepared_query("
            DELETE FROM torrents_artists WHERE GroupID = ?
            ", $this->id
        );
        $logger    = new Log;
        $artistMan = new Manager\Artist;
        foreach ($artistList as $artistId) {
            $artist = $artistMan->findById($artistId);
            if ($artist) {
                if ($artist->usageTotal() === 0) {
                    $artist->remove($user, $logger);
                } else {
                    $this->flush();
                }
            }
        }

        // Bookmarks
        self::$db->prepared_query("
            DELETE FROM bookmarks_torrents WHERE GroupID = ?
            ", $this->id
        );

        // Collages
        self::$db->prepared_query("
            SELECT CollageID FROM collages_torrents WHERE GroupID = ?
            ", $this->id
        );
        $CollageIDs = self::$db->collect(0, false);
        if ($CollageIDs) {
            self::$db->prepared_query("
                UPDATE collages SET
                    NumTorrents = NumTorrents - 1
                WHERE ID IN (" . placeholders($CollageIDs) . ")
                ", ...$CollageIDs
            );
            self::$db->prepared_query("
                DELETE FROM collages_torrents WHERE GroupID = ?
                ", $this->id
            );
            foreach ($CollageIDs as $CollageID) {
                self::$cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $CollageID));
            }
            self::$cache->delete_value("torrent_collages_" . $this->id);
        }

        (new \Gazelle\Manager\Comment)->remove('torrents', $this->id);

        // Requests
        self::$db->prepared_query("
            SELECT ID FROM requests WHERE GroupID = ?
            ", $this->id
        );
        $Requests = self::$db->collect(0, false);
        self::$db->prepared_query("
            UPDATE requests SET
                GroupID = NULL
            WHERE GroupID = ?
            ", $this->id
        );
        foreach ($Requests as $RequestID) {
            self::$cache->delete_value("request_$RequestID");
        }

        self::$db->prepared_query("
            DELETE FROM torrent_group_has_attr WHERE TorrentGroupID = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM torrents_tags WHERE GroupID = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM torrents_tags_votes WHERE GroupID = ?
            ", $this->id
        );
        self::$db->prepared_query("
            DELETE FROM wiki_torrents WHERE PageID = ?
            ", $this->id
        );

        $manager = new \Gazelle\DB;
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents_group', [['ID', $this->id]]);
        if (!$ok) {
            return false;
        }

        if ($this->categoryName() === 'Music') {
            self::$cache->decrement('stats_album_count');
        }
        self::$cache->decrement('stats_group_count');

        self::$cache->delete_multi([
            "groups_artists_{$this->id}",
            "torrent_group_{$this->id}",
            sprintf(self::CACHE_KEY, $this->id),
            sprintf(self::CACHE_TLIST_KEY, $this->id),
            sprintf(\Gazelle\Manager\TGroup::ID_KEY, $this->id),
            sprintf(\Gazelle\Manager\Torrent::CACHE_KEY_LATEST_UPLOADS, 5),
        ]);
        return true;
    }

    public function rename(string $name, User $user, Manager\TGroup $manager, Log $logger): bool {
        $oldName = $this->name();
        $success = $this->setField('Name', $name)->modify();
        if ($success) {
            $this->refresh();
            $logger->group($this->id, $user->id(), "renamed to \"$name\" from \"$oldName\"")
                ->general("Torrent Group {$this->id} was renamed to \"$name\" from \"$oldName\" by {$user->username()}");
        }
        return $success;
    }

    /**
     * Return info about the deleted masterings of a torrent group.
     *
     * @return array of strings imploded by '!!'
     *  [torrent_id, remastered, title, year, record_label, catalogue_number]
     */
    public function deletedMasteringList(): array {
        self::$db->prepared_query("
            SELECT d.Media                                                            AS media,
                d.Remastered = '1'                                                    AS remastered,
                d.RemasterTitle                                                       AS title,
                if(d.Remastered = '1', d.RemasterYear, tg.Year)                       AS year,
                if(d.Remastered = '1', d.RemasterRecordLabel, tg.RecordLabel)         AS record_label,
                if(d.Remastered = '1', d.RemasterCatalogueNumber, tg.CatalogueNumber) AS catalogue_number
            FROM deleted_torrents d
            LEFT JOIN torrents_group tg ON (tg.ID = d.GroupID)
            WHERE d.GroupID = ?
            GROUP BY remastered, year, title, record_label, catalogue_number, media
            ORDER BY remastered, year, title, record_label, catalogue_number, media
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
