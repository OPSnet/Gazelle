<?php

namespace Gazelle;

class TGroup extends BaseObject {

    const CACHE_KEY                = 'tg_%d';
    const CACHE_TLIST_KEY          = 'tlist_%d';

    const ARTIST_DISPLAY_TEXT = 1;
    const ARTIST_DISPLAY_HTML = 2;

    protected $artistDisplay = self::ARTIST_DISPLAY_HTML;
    protected $revisionId = 0;
    protected $showFallbackImage = true;
    protected $viewer;

    public function tableName(): string {
        return 'torrents_group';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    public function flush() {
    }

    public function setViewer(User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * Toggle whether an internal URL is returnd for missing cover artwork
     * is returned, or null. Used by API endpoints.
     *
     * @param bool false means the property will be null instead of placeholder URL
     * @return $this to allow method chaining
     */
    public function showFallbackImage(bool $showFallbackImage) {
        $this->showFallbackImage = $showFallbackImage;
        return $this;
    }

    public function revisionList(): array {
         $this->db->prepared_query("
            SELECT RevisionID AS revision,
                Summary       AS summary,
                Time          AS time,
                UserID        AS user_id
            FROM wiki_torrents
            WHERE PageID = ?
            ORDER BY RevisionID DESC
            ", $this->id
        );
        return $this->db->to_array('revision', MYSQLI_ASSOC, false);
    }

    /**
     * Get the metadata of the torrent
     *
     * @return array of many things
     */
    public function info(int $revisionId = 0): ?array {
        $key = sprintf(self::CACHE_KEY, $this->id);
        $this->revisionId = $revisionId;
        if (!$revisionId) {
            $cached = $this->cache->get_value($key);
            if (is_array($cached)) {
                // return $cached;
            }
        }
        $sql = 'SELECT '
            . ($this->revisionId ? 'w.Body, w.Image,' : 'g.WikiBody, g.WikiImage,')
            . " g.ID,
                g.Name,
                g.Year,
                g.RecordLabel,
                g.CatalogueNumber,
                g.ReleaseType,
                g.CategoryID,
                g.Time,
                g.VanityHouse,
                group_concat(DISTINCT tags.Name SEPARATOR '|') AS tagNames,
                group_concat(DISTINCT tags.ID SEPARATOR '|')   AS tagIds,
                group_concat(tt.UserID SEPARATOR '|')          AS tagVoteUserIds,
                group_concat(tt.PositiveVotes SEPARATOR '|')   AS tagUpvotes,
                group_concat(tt.NegativeVotes SEPARATOR '|')   AS tagDownvotes
            FROM torrents_group AS g
            LEFT JOIN torrents_tags AS tt ON (tt.GroupID = g.ID)
            LEFT JOIN tags ON (tags.ID = tt.TagID)
        ";

        $args = [];
        if ($this->revisionId) {
            $sql .= '
                LEFT JOIN wiki_torrents AS w ON (w.PageID = ? AND w.RevisionID = ?)';
            $args[] = $this->id;
            $args[] = $this->revisionId;
        }
        $sql .= " WHERE g.ID = ? GROUP BY g.ID";
        $args[] = $this->id;

        $info = $this->db->rowAssoc($sql, ...$args);

        // make the values sane (null, boolean as appropriate)
        // TODO: once all get_*_info calls have been ported over, do this prior to caching
        foreach (['CatalogueNumber', 'RecordLabel'] as $nullable) {
            $info[$nullable] = $info[$nullable] == '' ? null : $info[$nullable];
        }
        if (!$info['WikiImage']) {
            $info['WikiImage'] = $this->showFallbackImage
                ? (STATIC_SERVER . '/common/noartwork/' . CATEGORY_ICON[$info['CategoryID'] - 1])
                : null;
        }
        $info['VanityHouse'] = ($info['VanityHouse'] == 1);
        $info['ReleaseType'] = (int)$info['ReleaseType'];

        // Reorganize tag info to be useful
        $tagIds         = explode('|', $info['tagIds']);
        $tagNames       = explode('|', $info['tagNames']);
        $tagVoteUserIds = explode('|', $info['tagVoteUserIds']);
        $tagUpvotes     = explode('|', $info['tagUpvotes']);
        $tagDownvotes   = explode('|', $info['tagDownvotes']);
        $info['tags']   = [];
        for ($n = 0; $n < count($tagIds); ++$n) {
            $info['tags'][$tagIds[$n]] = [
                'name'      => $tagNames[$n],
                'userId'    => $tagVoteUserIds[$n],
                'upvotes'   => $tagUpvotes[$n],
                'downvotes' => $tagDownvotes[$n],
            ];
        }

        if (!$this->revisionId) {
            $this->cache->cache_value($key, $info, 0);
        }
        $info['Flags'] = [];
        $info['Flags']['IsSnatched'] = ($this->viewer && $this->viewer->option('ShowSnatched'))
            ? $this->db->scalar("
                SELECT 1 
                FROM torrents_group tg
                WHERE exists(
                        SELECT 1
                        FROM torrents t
                        INNER JOIN xbt_snatched xs ON (xs.fid = t.ID)
                        WHERE t.GroupID = tg.ID
                    )
                    AND tg.ID = ?
                ", $this->id)
            : false;
        return $info;
    }

    /**
     * Get artist list
     *
     * return array artists group by role
     */
    public function artistRole(): array {
        $key = 'shortv2_groups_artists_' . $this->id;
        $roleList = $this->cache->get_value($key);
        if ($roleList === false) {
            $this->db->prepared_query("
                SELECT ta.Importance,
                    ta.ArtistID,
                    aa.Name,
                    ta.AliasID
                FROM torrents_artists AS ta
                INNER JOIN artists_alias AS aa ON (ta.AliasID = aa.AliasID)
                WHERE ta.GroupID = ?
                ORDER BY ta.GroupID, ta.Importance ASC, aa.Name ASC
                ", $this->id
            );
            $map = [
                1 => 'main',
                2 => 'guest',
                3 => 'remixer',
                4 => 'composer',
                5 => 'conductor',
                6 => 'dj',
                7 => 'producer',
                8 => 'arranger',
            ];
            $roleList = [
                'main'      => [],
                'guest'     => [],
                'remixer'   => [],
                'composer'  => [],
                'conductor' => [],
                'dj'        => [],
                'producer'  => [],
                'arranger'  => [],
            ];
            while ([$role, $artistId, $artistName, $aliasId] = $this->db->next_record(MYSQLI_NUM, false)) {
                $roleList[$map[$role]][] = [
                    'id'      => $artistId,
                    'aliasid' => $aliasId,
                    'name'    => $artistName,
                ];
            }
            $this->cache->cache_value($key, $roleList, 3600);
        }
        return $roleList;
    }

    public function categoryId(): int {
        return $this->info()['CategoryID'];
    }

    public function name(): string {
        return $this->info()['Name'];
    }

    public function year(): string {
        return $this->info()['Year'];
    }

    public function label(): string {
        return $this->id() . " (" . $this->info()['Name'] . ")";
    }

    public function isSnatched(): bool {
        return $info['Flags']['IsSnatched'] ?? false;
    }

    public function artistName(): string {
        return $this->artistHtml(self::ARTIST_DISPLAY_TEXT);
    }

    public function torrentTagList(): array {
        $tag = [];
        foreach ($this->info()['tags'] as $t) {
            $tag[] = "<a href=\"torrents.php?taglist={$t['name']}\">{$t['name']}</a>";
        }
        return $tag;
    }

    /**
     * Generate an HTML anchor or the name for an artist
     */
    protected function artistLink(array $info, int $renderMode): string {
        return $renderMode === self::ARTIST_DISPLAY_HTML
            ? '<a href="artist.php?id=' . $info['id'] . '" dir="ltr">' . display_str($info['name']) . '</a>'
            : $info['name'];
    }

    /**
     * Generate the artist name. (Individual artists will be clickable, or VA)
     * TODO: refactor calls into artistName()
     */
    public function artistHtml(int $renderMode = self::ARTIST_DISPLAY_HTML): string {
        static $nameCache = [self::ARTIST_DISPLAY_HTML => [], self::ARTIST_DISPLAY_TEXT => []];
        if (isset($nameCache[$renderMode][$this->id])) {
            return $nameCache[$renderMode][$this->id];
        }

        $roleList = $this->artistRole();
        $composerCount = count($roleList['composer']);
        $conductorCount = count($roleList['conductor']);
        $arrangerCount = count($roleList['arranger']);
        $djCount = count($roleList['dj']);
        $mainCount = count($roleList['main']);
        if ($composerCount + $mainCount + $conductorCount + $djCount == 0) {
            return $nameCache[$renderMode][$this->id] = sprintf('(torrent id:%d)', $this->id);
        }

        $and = $renderMode === self::ARTIST_DISPLAY_HTML ? ' &amp; ' : ' & ';
        $chunk = [];
        if ($djCount == 1) {
            $chunk[] = $this->artistLink($roleList['dj'][0], $renderMode);
        } elseif ($djCount == 2) {
            $chunk[] = $this->artistLink($roleList['dj'][0], $renderMode) . $and . $this->artistLink($roleList['dj'][1], $renderMode);
        } elseif ($djCount > 2) {
            $chunk[] = 'Various DJs';
        } else {
            if ($composerCount > 0) {
                if ($composerCount == 1) {
                    $chunk[] = $this->artistLink($roleList['composer'][0], $renderMode);
                } elseif ($composerCount == 2) {
                    $chunk[] = $this->artistLink($roleList['composer'][0], $renderMode) . $and . $this->artistLink($roleList['composer'][1], $renderMode);
                } else {
                    $chunk[] = 'Various Composers';
                }
                if ($mainCount + $conductorCount > 0) {
                    $chunk[] = 'performed by';
                }
            }

            if ($composerCount > 0
                && $mainCount > 1
                && $conductorCount > 1
            ) {
                $chunk[] = 'Various Artists';
            } else {
                if ($mainCount == 1) {
                    $chunk[] = $this->artistLink($roleList['main'][0], $renderMode);
                } elseif ($mainCount == 2) {
                    $chunk[] = $this->artistLink($roleList['main'][0], $renderMode) . $and . $this->artistLink($roleList['main'][1], $renderMode);
                } elseif ($mainCount > 2) {
                    $chunk[] = 'Various Artists';
                }

                if ($conductorCount > 0
                    && $mainCount + $composerCount > 0
                    && ($composerCount < 3 || $mainCount > 0)
                ) {
                    $chunk[] = 'under';
                }
                if ($conductorCount == 1) {
                    $chunk[] = $this->artistLink($roleList['conductor'][0], $renderMode);
                } elseif ($conductorCount == 2) {
                    $chunk[] = $this->artistLink($roleList['conductor'][0], $renderMode) . $and . $this->artistLink($roleList['conductor'][1], $renderMode);
                } elseif ($conductorCount > 2) {
                    $chunk[] = 'Various Conductors';
                }
            }
        }
        return $nameCache[$renderMode][$this->id] = implode(' ', $chunk);
    }

    /**
     * Add artists to a group. The role and name arrays must be the same length, and
     * are walked down in step, to match the artist with their role in the group
     *
     * param \Gazelle\User who is adding
     * @param array list of artist roles
     * @param array list of artist names (unknown artists will be created)
     * @return int number of artists added
     */
    public function addArtists(\Gazelle\User $user, array $roles, array $names): int {
        $userId = $user->id();
        $artistMan = new \Gazelle\Manager\Artist;
        $add = [];
        $args = [];
        $n = count($names);
        for ($i = 0; $i < $n; $i++) {
            $role = $roles[$i];
            if (!in_array($role, array_keys(ARTIST_TYPE))) {
                continue;
            }
            $name = \Gazelle\Artist::sanitize($names[$i]);
            if (!$name) {
                return 0;
            }
            [$artistId, $aliasId] = $artistMan->fetchArtistIdAndAliasId($name);
            if ($artistId) {
                array_push($args, $this->id, $userId, $artistId, $aliasId, $role, (string)$role);
                $add[] = "$artistId ($name) as " . ARTIST_TYPE[$role];
            }
        }
        if (empty($add)) {
            return 0;
        }
        try {
            $this->db->prepared_query("
                INSERT INTO torrents_artists
                       (GroupID, UserID, ArtistID, AliasID, artist_role_id, Importance)
                VALUES " . placeholders($add, '(?, ?, ?, ?, ?, ?)')
                , ...$args
            );
        } catch (\DB_MYSQL_DuplicateKeyException $e) {
            return 0;
        }

        $logger = new \Gazelle\Log;
        $userLabel = "$userId (" .  $user->username() . ")";
        foreach ($add as $artistLabel) {
            $logger->group($this->id, $user->id(), "Added artist $artistLabel")
                ->general("Artist $artistLabel was added to the group " . $this->id . " (" . $this->name() . ") by user $userLabel");
        }
        return count($add);
    }

    public function removeArtist(int $artistId, int $role): bool {
        $this->db->prepared_query('
            DELETE FROM torrents_artists
            WHERE GroupID = ?
                AND ArtistID = ?
                AND Importance = ?
            ', $this->id, $artistId, $role
        );
        if (!$this->db->affected_rows()) {
            return false;
        }
        $unused = (bool)$this->db->scalar("
            SELECT 1
            FROM artists_group ag
            LEFT JOIN torrents_artists ta USING (ArtistID)
            LEFT JOIN requests_artists ra USING (ArtistID)
            WHERE ta.ArtistID IS NULL
                AND ra.artistID IS NULL
                AND ag.ArtistID = ?
            ", $artistId
        );
        if ($unused) {
            // The last group to use this artist
            \Artists::delete_artist($artistId);
        }
        return true;
    }

    public function torrentList(): array {
        $viewerId = $this->viewer ? $this->viewer->id() : 0;
        $showSnatched = $viewerId ? $this->viewer->option('ShowSnatched') : false;
        $list = $this->rawTorrentList();
        foreach ($list as &$info) {
            foreach (['last_action', 'LastReseedRequest', 'RemasterCatalogueNumber', 'RemasterRecordLabel', 'RemasterTitle', 'RemasterYear']
                as $nullable
            ) {
                $info[$nullable] = $info[$nullable] == '' ? null : $info[$nullable];
            }
            foreach (['LogChecksum', 'HasCue', 'HasLog', 'HasLogDB', 'Remastered', 'Scene']
                as $zerotruth
            ) {
                $info[$zerotruth] = !($info[$zerotruth] == '0');
            }
            foreach (['BadFiles', 'BadFolders', 'BadTags', 'CassetteApproved', 'LossymasterApproved', 'LossywebApproved', 'MissingLineage']
                as $emptytruth
            ) {
                $info[$emptytruth] = !($info[$emptytruth] == '');
            }
            if ($viewerId) {
                $torrent = new Torrent($info['ID']);
                $info['PersonalFL'] = $info['FreeTorrent'] == '0' && $torrent->hasToken($viewerId);
                $info['IsSnatched'] = $showSnatched && $torrent->isSnatched($viewerId);
            } else {
                $info['PersonalFL'] = false;
                $info['IsSnatched'] = false;
            }
        }
        return $list;
    }

    public function rawTorrentList(): array {
        $key = sprintf(self::CACHE_TLIST_KEY, $this->id);
        if (!$this->revisionId) {
            $list = $this->cache->get_value($key);
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

        $this->db->prepared_query("
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
        $list = $this->db->to_array('ID', MYSQLI_ASSOC, false);
        if (empty($list)) {
            return [];
        }
        if (!$this->revisionId) {
            $this->cache->cache_value($key, $list, in_array(0, $this->db->collect('Seeders')) ? 600 : 3600);
        }
        return $list;
    }

    /**
     * How many unresolved torrent reports are there in this group?
     * @param int Group ID
     * @return int number of unresolved reports
     */
    public function unresolvedReportsTotal(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.GroupID = ?
            ", $this->id
        );
    }

    public function expireToken(int $userId, int $torrentId): bool {
        $hash = $this->db->scalar("
            SELECT info_hash FROM torrents WHERE ID = ?
            ", $torrentId
        );
        if (!$hash) {
            return false;
        }
        $this->db->prepared_query("
            UPDATE users_freeleeches SET
                Expired = true
            WHERE UserID = ?
                AND TorrentID = ?
            ", $userId, $torrentId
        );
        $this->cache->delete_value("users_tokens_{$userId}");
        (new \Gazelle\Tracker)->update_tracker('remove_token', ['info_hash' => rawurlencode($hash), 'userid' => $userId]);
        return true;
    }

    public function missingLogfiles(int $userId): array {
        $this->db->prepared_query("
            SELECT ID, GroupID, `Format`, Encoding, HasCue, HasLog, HasLogDB, LogScore, LogChecksum
            FROM torrents
            WHERE HasLog = '1' AND HasLogDB = '0' AND UserID = ?
            ", $userId
        );
        if (!$this->db->has_results()) {
            return [];
        }
        $GroupIDs = $this->db->collect('GroupID');
        $TorrentsInfo = $this->db->to_array('ID');
        $Groups = \Torrents::get_groups($GroupIDs);

        $result = [];
        foreach ($TorrentsInfo as $TorrentID => $Torrent) {
            [$ID, $GroupID, $Format, $Encoding, $HasCue, $HasLog, $HasLogDB, $LogScore, $LogChecksum] = $Torrent;
            $Group = $Groups[$GroupID];
            $GroupName = $Group['Name'];
            $GroupYear = $Group['Year'];
            $ExtendedArtists = $Group['ExtendedArtists'];
            $Artists = $Group['Artists'];
            if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
                unset($ExtendedArtists[2]);
                unset($ExtendedArtists[3]);
                $DisplayName = \Artists::display_artists($ExtendedArtists);
            } elseif (!empty($Artists)) {
                $DisplayName = \Artists::display_artists([1 => $Artists]);
            } else {
                $DisplayName = '';
            }
            $DisplayName .= '<a href="torrents.php?id='.$GroupID.'&amp;torrentid='.$ID.'" class="tooltip" title="View torrent" dir="ltr">'.$GroupName.'</a>';
            if ($GroupYear > 0) {
                $DisplayName .= " [{$GroupYear}]";
            }
            $Info = [];
            if (strlen($Format)) {
                $Info[] = $Format;
            }
            if (strlen($Encoding)) {
                $Info[] = $Encoding;
            }
            if (!empty($Info)) {
                $DisplayName .= ' [' . implode('/', $Info) . ']';
            }
            if ($HasLog == '1') {
                $DisplayName .= ' / Log'.($HasLogDB == '1' ? " ({$LogScore}%)" : "");
            }
            if ($HasCue == '1') {
                $DisplayName .= ' / Cue';
            }
            if ($LogChecksum == '0') {
                $DisplayName .= ' / ' . \Format::torrent_label('Bad/Missing Checksum');
            }
            $result[$ID] = $DisplayName;
        }
        return $result;
    }

    public function addTagVote(int $userId, int $tagId, string $way): int {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            SELECT TagID
            FROM torrents_tags_votes
            WHERE GroupID = ?
                AND TagID = ?
                AND UserID = ?
                AND Way = ?
            ", $this->id, $tagId, $userId, $way
        );
        if ($this->db->has_results()) {
            $this->db->rollback();
            return 0;
        }
        if ($way == 'down') {
            $change = 'NegativeVotes = NegativeVotes + 1';
        } else {
            $change = 'PositiveVotes = PositiveVotes + 2';
        }
        $this->db->prepared_query("
            UPDATE torrents_tags SET
                $change
            WHERE GroupID = ?
                AND TagID = ?
            ", $this->id, $tagId
        );
        $this->db->prepared_query("
            INSERT INTO torrents_tags_votes
                   (GroupID, TagID, UserID, Way)
            VALUES (?,       ?,     ?,      ?)
            ", $this->id, $tagId, $userId, $way
        );
        $n = $this->db->affected_rows();
        $this->db->commit();
        $this->cache->deleteMulti(['tg_' . $this->id, 'torrents_details_' . $this->id]);
        return $n;
    }
}
