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

    public function tableName(): string {
        return 'torrents_group';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    public function flush() {
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
            if (!$this->showFallbackImage) {
                $info['WikiImage'] = null;
            } else {
                global $CategoryIcons;
                $info['WikiImage'] = STATIC_SERVER . '/common/noartwork/' . $CategoryIcons[$info['CategoryID'] - 1];
            }
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

    public function name(): string {
        return $this->info()['Name'];
    }

    public function artistName(): string {
        return $this->artistHtml(self::ARTIST_DISPLAY_TEXT);
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

        $and = $renderMode === self::ARTIST_DISPLAY_HTML ? '&amp;' : '&';
        $chunk = [];
        if ($djCount == 1) {
            $chunk[] = $this->artistLink($roleList['dj'][0], $renderMode);
        } elseif ($djCount == 2) {
            $chunk[] = $this->artistLink($roleList['dj'][0], $renderMode) . " $and " . $this->artistLink($roleList['dj'][1], $renderMode);
        } elseif ($djCount > 2) {
            $chunk[] = 'Various DJs';
        } else {
            if ($composerCount > 0) {
                if ($composerCount == 1) {
                    $chunk[] = $this->artistLink($roleList['composer'][0], $renderMode);
                } elseif ($composerCount == 2) {
                    $chunk[] = $this->artistLink($roleList['composer'][0], $renderMode) . " $and " . $this->artistLink($roleList['composer'][1], $renderMode);
                } elseif ($composerCount > 2 && $mainCount + $conductorCount == 0) {
                    $chunk[] = 'Various Composers';
                }
                if ($mainCount > 0) {
                    $chunk[] = 'Various Composers performed by';
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
                    $chunk[] = $this->artistLink($roleList['main'][0], $renderMode) . " $and " . $this->artistLink($roleList['main'][1], $renderMode);
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
                    $chunk[] = $this->artistLink($roleList['conductor'][0], $renderMode) . " $and " . $this->artistLink($roleList['conductor'][1], $renderMode);
                } elseif ($conductorCount > 2) {
                    $chunk[] = 'Various Conductors';
                }
            }
        }
        return $nameCache[$renderMode][$this->id] = implode(' ', $chunk);
    }

    public function torrentList(): array {
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
}
