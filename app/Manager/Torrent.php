<?php

namespace Gazelle\Manager;

use Gazelle\Exception\TorrentManagerIdNotSetException;
use Gazelle\Exception\TorrentManagerUserNotSetException;

class Torrent extends \Gazelle\Base {
    /*
     **** To display a torrent name, edition and flags, at the minimum the code looks like:

        $torMan = new Gazelle\Manager\Torrent;
        $labelMan = new Gazelle\Manager\TorrentLabel;

        // set up the labeler once
        $labelMan->showMedia(true)->showEdition(true);

        // load the group and torrent ids (normally both of these are always at hand)
        $torMan->setGroupId(1234)->setTorrentId(1666);

        // if only the torrentId is set, it will discover the groupId
        $torMan->setTorrentId(1666);

        // the artist name (A, A & B, Various Artists, Various Composers under Various Conductors etc)
        echo $torMan->artistHtml();

        // load the torrent details into the labeler
        $labelMan->load($torMan->torrentInfo()[1]);

        // remaster info, year, etc
        echo $labelMan->edition();

        // flags (Reported, Freeleech, Lossy WEB Approved, etc
        echo $labelMan->label();

    **** This is a bit cumbersome and subject to change
    */

    protected $torrentId;
    protected $groupId;
    protected $userId;
    protected $showSnatched;
    protected $snatchBucket;
    protected $updateTime;
    protected $tokenCache;
    protected $artistDisplay;
    protected $showFallbackImage = true;
    protected $logger;

    const FEATURED_AOTM     = 0;
    const FEATURED_SHOWCASE = 1;

    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_';
    const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';
    const CACHE_KEY_FEATURED       = 'featured_%d';

    const FILELIST_DELIM_UTF8 = "\xC3\xB7";

    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists
    const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    const ARTIST_DISPLAY_TEXT = 1;
    const ARTIST_DISPLAY_HTML = 2;

    public function __construct() {
        parent::__construct();
        $this->artistDisplay = self::ARTIST_DISPLAY_HTML;
    }

    public function findGroupById(int $groupId) {
        $id = $this->db->scalar("
            SELECT ID FROM torrents_group WHERE ID = ?
            ", $groupId
        );
        return $id ? (new \Gazelle\TorrentGroup($id))->setInfo($this->groupInfo($id)) : null;
    }

    public function findTorrentById(int $torrentId) {
        $id = $this->db->scalar("
            SELECT ID FROM torrents WHERE ID = ?
            ", $torrentId
        );
        return $id ? new \Gazelle\Torrent($id) : null;
    }

    public function findTorrentByHash(string $hash) {
        $id = $this->hashToTorrentId($hash);
        return $id ? new \Gazelle\Torrent($id) : null;
    }

    /**
     * Set context to a specific group. Used to retrieve the
     * information needed to build a complete url.
     *
     * @param int $groupId The ID of the group
     */
    public function setGroupId(int $groupId) {
        $this->groupId = $groupId;
        return $this;
    }

    /**
     * Set context to a specific torrent. Used to retrieve the
     * information needed to build a complete url.
     *
     * @param int $torrentId The ID of the torrent (not the group!)
     * @return $this to allow method chaining
     */
    public function setTorrentId(int $torrentId) {
        if ($this->torrentId !== $torrentId) {
            $this->groupId = null;
        }
        $this->torrentId = $torrentId;
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

    /**
     * Set context to a specific user. Used to determine whether or not to display
     * Personal Freeleech and Snatched indicators in torrent and group info.
     *
     * @param int $userId The ID of the User
     * @return $this to allow method chaining
     */
    public function setViewer(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set artist display to text
     */
    public function setArtistDisplayText() {
        $this->artistDisplay = self::ARTIST_DISPLAY_TEXT;
        return $this;
    }

    public function artistName(): string {
        return $this->artistHtml();
    }

    /**
     * Generate an HTML anchor or the name for an artist
     */
    protected function artistLink(array $info): string {
        return $this->artistDisplay === self::ARTIST_DISPLAY_HTML
            ? '<a href="artist.php?id=' . $info['id'] . '" dir="ltr">' . display_str($info['name']) . '</a>'
            : $info['name'];
    }

    /**
     * Get artist list
     *
     * return array artists group by role
     */
    public function artistRole(): array {
        $key = 'shortv2_groups_artists_' . $this->groupId;
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
                ", $this->groupId
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

    /**
     * Generate the artist name. (Individual artists will be clickable, or VA)
     * TODO: refactor calls into artistName()
     */
    public function artistHtml(): string {
        static $nameCache = [];
        if (isset($nameCache[$this->torrentId])) {
            return $nameCache[$this->torrentId];
        }

        if (!$this->groupId) {
            $this->groupId = $this->idToGroupId($this->torrentId);
        }
        if (!$this->torrentId && !$this->groupId) {
            return $nameCache[$this->torrentId] = '';
        }
        $roleList = $this->artistRole();
        $composerCount = count($roleList['composer']);
        $conductorCount = count($roleList['conductor']);
        $arrangerCount = count($roleList['arranger']);
        $djCount = count($roleList['dj']);
        $mainCount = count($roleList['main']);
        if ($composerCount + $mainCount + $conductorCount + $djCount == 0) {
            return $nameCache[$this->torrentId] = sprintf('(torrent id:%d)', $this->torrentId);
        }

        $and = $this->artistDisplay === self::ARTIST_DISPLAY_HTML ? '&amp;' : '&';
        $chunk = [];
        if ($djCount == 1) {
            $chunk[] = $this->artistLink($roleList['dj'][0]);
        } elseif ($djCount == 2) {
            $chunk[] = $this->artistLink($roleList['dj'][0]) . " $and " . $this->artistLink($roleList['dj'][1]);
        } elseif ($djCount > 2) {
            $chunk[] = 'Various DJs';
        } else {
            if ($composerCount > 0) {
                if ($composerCount == 1) {
                    $chunk[] = $this->artistLink($roleList['composer'][0]);
                } elseif ($composerCount == 2) {
                    $chunk[] = $this->artistLink($roleList['composer'][0]) . " $and " . $this->artistLink($roleList['composer'][1]);
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
                    $chunk[] = $this->artistLink($roleList['main'][0]);
                } elseif ($mainCount == 2) {
                    $chunk[] = $this->artistLink($roleList['main'][0]) . " $and " . $this->artistLink($roleList['main'][1]);
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
                    $chunk[] = $this->artistLink($roleList['conductor'][0]);
                } elseif ($conductorCount == 2) {
                    $chunk[] = $this->artistLink($roleList['conductor'][0]) . " $and " . $this->artistLink($roleList['conductor'][1]);
                } elseif ($conductorCount > 2) {
                    $chunk[] = 'Various Conductors';
                }
            }
        }
        return $nameCache[$this->torrentId] = implode(' ', $chunk);
    }

    /**
     * Delete a torrent.
     * setViewer() must have been called prior, to set the user removing (use 0 for system)
     * setTorrentId() must have been called prior, to identify the torrent to be removed
     *
     * @param string $reason Why is this being deleted? (For the log)
     * @param string $trackerReason The deletion reason for ocelot to report to users.
     */
    public function remove(string $reason, int $trackerReason = -1): array {
        $qid = $this->db->get_query_id();
        if (!$this->torrentId) {
            throw new TorrentManagerIdNotSetException;
        }
        if ($this->userId === null) {
            throw new TorrentManagerUserNotSetException;
        }

        [$group, $torrent] = $this->torrentInfo();
        if ($this->torrentId > MAX_PREV_TORRENT_ID) {
            (new \Gazelle\Bonus)->removePointsForUpload($torrent['UserID'],
                [$torrent['Format'], $torrent['Media'], $torrent['Encoding'], $torrent['HasLogDB'], $torrent['LogScore'], $torrent['LogChecksum']]);
        }

        $manager = new \Gazelle\DB;
        $manager->relaxConstraints(true);
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents_leech_stats', [['TorrentID', $this->torrentId]], false);
        if (!$ok) {
            return [false, $message];
        }
        [$ok, $message] = $manager->softDelete(SQLDB, 'torrents', [['ID', $this->torrentId]]);
        if (!$ok) {
            return [false, $message];
        }
        $manager->relaxConstraints(false);
        (new \Gazelle\Tracker)->update_tracker('delete_torrent', [
            'id' => $this->torrentId,
            'info_hash' => rawurlencode(hex2bin($torrent['InfoHash'])),
            'reason' => $trackerReason,
        ]);
        $this->cache->decrement('stats_torrent_count');

        $Count = $this->db->scalar("
            SELECT count(*) FROM torrents WHERE GroupID = ?
            ", $group['ID']
        );
        if ($Count > 0) {
            \Torrents::update_hash($group['ID']);
        }

        $manager->softDelete(SQLDB, 'torrents_files',                  [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_bad_files',              [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_bad_folders',            [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_bad_tags',               [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_cassette_approved',      [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_lossymaster_approved',   [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_lossyweb_approved',      [['TorrentID', $this->torrentId]]);
        $manager->softDelete(SQLDB, 'torrents_missing_lineage',        [['TorrentID', $this->torrentId]]);

        $this->db->prepared_query("
            INSERT INTO user_torrent_remove
                (user_id, torrent_id)
            VALUES (?,       ?)
            ", $this->userId, $this->torrentId
        );

        // Tells Sphinx that the group is removed
        $this->db->prepared_query("
            REPLACE INTO sphinx_delta
                (ID, Time)
            VALUES (?, now())
            ", $this->torrentId
        );

        $this->db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ModComment = 'Report already dealt with (torrent deleted)'
            WHERE Status != 'Resolved'
                AND TorrentID = ?
            ", $this->torrentId
        );
        $count = $this->db->affected_rows();
        if ($count) {
            $this->cache->decrement('num_torrent_reportsv2', $count);
        }

        // Torrent notifications
        $this->db->prepared_query("
            SELECT concat('user_notify_upload_', UserID) as ck
            FROM users_notify_torrents
            WHERE TorrentID = ?
            ", $this->torrentId
        );
        $deleteKeys = $this->db->collect('ck', false);
        $manager->softDelete(SQLDB, 'users_notify_torrents', [['TorrentID', $this->torrentId]]);

        if ($this->userId !== 0) {
            $RecentUploads = $this->cache->get_value("user_recent_up_" . $this->userId);
            if (is_array($RecentUploads)) {
                foreach ($RecentUploads as $Key => $Recent) {
                    if ($Recent['ID'] == $group['ID']) {
                        $deleteKeys[] = "user_recent_up_" . $this->userId;
                        break;
                    }
                }
            }
        }

        $deleteKeys[] = "torrent_download_" . $this->torrentId;
        $deleteKeys[] = "torrent_group_" . $group['ID'];
        $deleteKeys[] = "torrents_details_" . $group['ID'];
        $this->cache->deleteMulti($deleteKeys);

        if (!$this->logger) {
            $this->logger = new \Gazelle\Log;
        }
        $infohash = strtoupper($torrent['InfoHash']);
        $sizeMB = number_format($torrent['Size'] / (1024 * 1024), 2) . ' MB';
        $username = $this->userId ? (new User)->findById($this->userId)->username() : 'system';
        $this->logger->general(
            "Torrent "
                . $this->torrentId
                . " ({$group['Name']}) [" . (new TorrentLabel)->load($torrent)->release()
                . "] ($sizeMB $infohash) was deleted by $username for reason: $reason"
            )
            ->torrent(
                $group['ID'],
                $this->torrentId,
                $this->userId,
                "deleted torrent ($sizeMB $infohash) for reason: $reason"
            );

        $this->db->set_query_id($qid);
        return [true, "torrent " . $this->torrentId . " removed"];
    }

    /**
     * In the context of a user, determine whether snatched indicators should be
     * added to torrent and group info.
     *
     * @param int $userID The ID of the User
     * @return $this to allow method chaining
     */
    public function setShowSnatched(int $showSnatched) {
        $this->showSnatched = $showSnatched;
        return $this;
    }

    /**
     * Has the viewing user snatched this torrent? (And do they want
     * to know about it?)
     *
     * @param int $torrentId
     * @return bool
     */
    public function isSnatched(int $torrentId): bool {
        if (!$this->userId || !$this->showSnatched) {
            return false;
        }

        $buckets = 64;
        $bucketMask = $buckets - 1;
        $bucketId = $torrentId & $bucketMask;

        $snatchKey = "users_snatched_" . $this->userId . "_time";
        if (!$this->snatchBucket) {
            $this->snatchBucket = array_fill(0, $buckets, false);
            $this->updateTime = $this->cache->get_value($snatchKey);
            if ($this->updateTime === false) {
                $this->updateTime = [
                    'last' => 0,
                    'next' => 0
                ];
            }
        } elseif (isset($this->snatchBucket[$bucketId][$torrentId])) {
            return true;
        }

        // Torrent was not found in the previously inspected snatch lists
        $bucket =& $this->snatchBucket[$bucketId];
        if ($bucket === false) {
            $now = time();
            // This bucket hasn't been checked before
            $bucket = $this->cache->get_value($snatchKey, true);
            if ($bucket === false || $now > $this->updateTime['next']) {
                $bucketKeyStem = 'users_snatched_' . $this->userId . '_';
                $updated = [];
                $qid = $this->db->get_query_id();
                if ($bucket === false || $this->updateTime['last'] == 0) {
                    for ($i = 0; $i < $buckets; $i++) {
                        $this->snatchBucket[$i] = [];
                    }
                    // Not found in cache. Since we don't have a suitable index, it's faster to update everything
                    $this->db->prepared_query("
                        SELECT fid
                        FROM xbt_snatched
                        WHERE uid = ?
                        ", $this->userId
                    );
                    while ([$id] = $this->db->next_record(MYSQLI_NUM, false)) {
                        $this->snatchBucket[$id & $bucketMask][(int)$id] = true;
                    }
                    $updated = array_fill(0, $buckets, true);
                } elseif (isset($bucket[$torrentId])) {
                    // Old cache, but torrent is snatched, so no need to update
                    return true;
                } else {
                    // Old cache, check if torrent has been snatched recently
                    $this->db->prepared_query("
                        SELECT fid
                        FROM xbt_snatched
                        WHERE uid = ? AND tstamp >= ?
                        ", $this->userId, $this->updateTime['last']
                    );
                    while ([$id] = $this->db->next_record(MYSQLI_NUM, false)) {
                        $bucketId = $id & $bucketMask;
                        if ($this->snatchBucket[$bucketId] === false) {
                            $this->snatchBucket[$bucketId] = $this->cache->get_value("$bucketKeyStem$bucketId", true);
                            if ($this->snatchBucket[$bucketId] === false) {
                                $this->snatchBucket[$bucketId] = [];
                            }
                        }
                        $this->snatchBucket[$bucketId][(int)$id] = true;
                        $updated[$bucketId] = true;
                    }
                }
                $this->db->set_query_id($qid);
                for ($i = 0; $i < $buckets; $i++) {
                    if (isset($updated[$i])) {
                        $this->cache->cache_value("$bucketKeyStem$i", $this->snatchBucket[$i], 7200);
                    }
                }
                $this->updateTime['last'] = $now;
                $this->updateTime['next'] = $now + self::SNATCHED_UPDATE_INTERVAL;
                $this->cache->cache_value($snatchKey, $this->updateTime, 7200);
            }
        }
        return isset($bucket[$torrentId]);
    }

    /**
     * Check if the viwer has an active freeleech token
     * setViewer() must be called beforehand
     *
     * @param int $torrentId
     * @return true if an active token exists for the viewer
     */
    public function hasToken(int $torrentId): bool {
        if (!$this->userId) {
            return false;
        }
        if (!$this->tokenCache) {
            $key = "users_tokens_" . $this->userId;
            $this->tokenCache = $this->cache->get_value($key);
            if ($this->tokenCache === false) {
                $qid = $this->db->get_query_id();
                $this->db->prepared_query("
                    SELECT TorrentID
                    FROM users_freeleeches
                    WHERE Expired = 0 AND UserID = ?
                    ",
                    $this->userId
                );
                $this->tokenCache = array_fill_keys($this->db->collect('TorrentID', false), true);
                $this->db->set_query_id($qid);
                $this->cache->cache_value($key, $this->tokenCache, 3600);
            }
        }
        return isset($this->tokenCache[$torrentId]);
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

    public function groupInfo(int $groupId, int $revisionId = 0): ?array {
        if (!$groupId) {
            return null;
        }
        $cached = null;
        if (!$revisionId) {
            $cached = $this->cache->get_value("torrents_details_$groupId");
        }
        if (!$revisionId && is_array($cached)) {
            [$group, $torrentList] = $cached;
        } else {
            // Fetch the group details

            $SQL = 'SELECT '
                . ($revisionId ? 'w.Body, w.Image,' : 'g.WikiBody, g.WikiImage,')
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
            if ($revisionId) {
                $SQL .= '
                    LEFT JOIN wiki_torrents AS w ON (w.PageID = ? AND w.RevisionID = ?)';
                $args[] = $groupId;
                $args[] = $revisionId;
            }

            $SQL .= "
                WHERE g.ID = ?
                GROUP BY g.ID";
            $args[] = $groupId;

            $this->db->prepared_query($SQL, ...$args);
            $group = $this->db->next_record(MYSQLI_ASSOC, false);

            // Fetch the individual torrents
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
                    t.ID AS HasFile,
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
                ", $groupId, $groupId
            );
            $torrentList = $this->db->to_array('ID', MYSQLI_ASSOC, false);
            if (empty($group) || empty($torrentList)) {
                return null;
            }

            if (!$revisionId) {
                $this->cache->cache_value("torrents_details_$groupId", [$group, $torrentList],
                    in_array(0, $this->db->collect('Seeders')) ? 600 : 3600
                );
            }
        }

        // Fetch all user specific torrent and group properties
        $group['Flags'] = ['IsSnatched' => false];
        foreach ($torrentList as &$t) {
            $t['PersonalFL'] = empty($t['FreeTorrent']) && $this->hasToken($t['ID']);
            if ($t['IsSnatched'] = $this->isSnatched($t['ID'])) {
                $group['IsSnatched'] = true;
            }
        }
        unset($t);

        // make the values sane (null, boolean as appropriate)
        // TODO: once all get_*_info calls have been ported over, do this prior to caching
        foreach (['CatalogueNumber', 'RecordLabel'] as $nullable) {
            $group[$nullable] = $group[$nullable] == '' ? null : $group[$nullable];
        }
        if (!$group['WikiImage']) {
            if (!$this->showFallbackImage) {
                $group['WikiImage'] = null;
            } else {
                global $CategoryIcons;
                $group['WikiImage'] = STATIC_SERVER.'/common/noartwork/'
                    . $CategoryIcons[$group['CategoryID'] - 1];
            }
        }
        $group['VanityHouse'] = ($group['VanityHouse'] == 1);
        $group['ReleaseType'] = (int)$group['ReleaseType'];

        // Reorganize tag info to be useful
        $tagIds = explode('|', $group['tagIds']);
        $tagNames = explode('|', $group['tagNames']);
        $tagVoteUserIds = explode('|', $group['tagVoteUserIds']);
        $tagUpvotes = explode('|', $group['tagUpvotes']);
        $tagDownvotes = explode('|', $group['tagDownvotes']);
        $group['tags'] = [];
        for ($n = 0; $n < count($tagIds); ++$n) {
            $group['tags'][$tagIds[$n]] = [
                'name' => $tagNames[$n],
                'userId' => $tagVoteUserIds[$n],
                'upvotes' => $tagUpvotes[$n],
                'downvotes' => $tagDownvotes[$n],
            ];
        }

        foreach ($torrentList as &$torrent) {
            foreach (['last_action', 'LastReseedRequest', 'RemasterCatalogueNumber', 'RemasterRecordLabel', 'RemasterTitle', 'RemasterYear']
                as $nullable
            ) {
                $torrent[$nullable] = $torrent[$nullable] == '' ? null : $torrent[$nullable];
            }
            foreach (['FreeTorrent', 'HasCue', 'HasLog', 'HasLogDB', 'LogChecksum', 'Remastered', 'Scene']
                as $zerotruth
            ) {
                $torrent[$zerotruth] = !($torrent[$zerotruth] == '0');
            }
            foreach (['BadFiles', 'BadFolders', 'BadTags', 'CassetteApproved', 'LossymasterApproved', 'LossywebApproved', 'MissingLineage']
                as $emptytruth
            ) {
                $torrent[$emptytruth] = !($torrent[$emptytruth] == '');
            }
            $torrent['ripLogIds'] = empty($torrent['ripLogIds'])
                ? [] : array_map(function ($x) { return (int)$x; },  explode(',', $torrent['ripLogIds']));
            $torrent['LogCount'] = count($torrent['ripLogIds']);
        }
        return [$group, $torrentList];
    }

    public function torrentInfo($revisionId = 0) {
        if (!$this->groupId) {
            $this->groupId = $this->idToGroupId($this->torrentId);
            if (!$this->groupId) {
                return null;
            }
        }

        if (!($info = $this->groupInfo($this->groupId, $revisionId))) {
            return null;
        }
        return [$info[0], $info[1][$this->torrentId]];
    }

    /**
     * Is this a valid torrenthash?
     * @param string $hash
     * @return string|bool The hash (with any spaces removed) if valid, otherwise false
     */
    public function isValidHash(string $hash) {
        //6C19FF4C 6C1DD265 3B25832C 0F6228B2 52D743D5
        $hash = str_replace(' ', '', $hash);
        return preg_match('/^[0-9a-fA-F]{40}$/', $hash) ? $hash : false;
    }

    /**
     * Map a torrenthash to a torrent id
     * @param string $hash
     * @return int The torrent id if found, otherwise null
     */
    public function hashToTorrentId(string $hash): ?int {
        return $this->db->scalar("
            SELECT ID FROM torrents WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrenthash to a group id
     * @param string $hash
     * @return int The group id if found, otherwise null
     */
    public function hashToGroupId(string $hash) {
        return $this->db->scalar("
            SELECT GroupID FROM torrents WHERE info_hash = UNHEX(?)
            ", $hash
        );
    }

    /**
     * Map a torrenthash to a torrent id and its group id
     * @param string $hash
     * @return array The torrent id and group id if found, otherwise null
     */
    public function hashToTorrentGroup(string $hash) {
        $key = "thash_to_group_$hash";
        if (($info = $this->cache->get_value($key)) === false) {
            $info =  $this->db->row("
                SELECT ID, GroupID FROM torrents WHERE info_hash = UNHEX(?)
                ", $hash
            );
            $this->cache->cache_value($key, $info, 86400 + rand(0, 3600));
        }
        return $info;
    }

    /**
     * Map a torrent id to a group id
     * @param int $torrentId
     * @return int The group id if found, otherwise null
     */
    public function idToGroupId(int $torrentId) {
        $key = "tid_to_group_$torrentId";
        if (($groupId = $this->cache->get_value($key)) === false) {
            $groupId = $this->db->scalar("
                SELECT GroupID FROM torrents WHERE ID = ?
                ", $torrentId
            );
            $this->cache->cache_value($key, $groupId, 86400 + rand(0, 3600));
        }
        return $groupId;
    }

    /**
     * How many unresolved torrent reports are there in this group?
     * @param int Group ID
     * @return int number of unresolved reports
     */
    public function unresolvedGroupReports(int $groupId): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.GroupID = ?
            ", $groupId
        );
    }

    /**
     * How many unresolved torrent reports are there for this user?
     * @param int User ID
     * @return int number of unresolved reports
     */
    public function unresolvedUserReports(int $userId): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.UserID = ?
            ", $userId
        );
    }

    /**
     * Get the requests filled by this torrent.
     * (Should only be one, but hey, who knows what the original developer was looking to catch?)
     * @param int torrent ID
     * @return DB object to loop over [request id, filler user id, date filled]
     */
    public function requestFills(int $torrentId): array {
        $this->db->prepared_query("
            SELECT r.ID, r.FillerID, r.TimeFilled FROM requests AS r WHERE r.TorrentID = ?
            ", $torrentId
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    /**
     * Return the N most recent lossless uploads
     * Note that if both a Lossless and 24bit Lossless are uploaded at the same time,
     * only one entry will be returned, to ensure that the result is comprised of N
     * different groups. Uploads of paranoid users are excluded. Uploads without
     * cover art are excluded.
     *
     * @param int $limit
     * @return array of [imageUrl, groupId, torrentId, uploadDate, username, paranoia]
     */
    public function latestUploads(int $limit) {
        if (!($latest = $this->cache->get_value(self::CACHE_KEY_LATEST_UPLOADS . $limit))) {
            $this->db->prepared_query("
                SELECT tg.WikiImage AS imageUrl,
                    R.GroupID       AS groupId,
                    R.torrentId,
                    R.uploadDate,
                    um.Username     AS username,
                    um.Paranoia     AS paranoia,
                    group_concat(tag.Name ORDER BY tag.Name SEPARATOR ', ') AS tags
                FROM (
                    SELECT t.GroupID,
                        max(t.ID)   AS torrentId,
                        max(t.Time) AS uploadDate
                    FROM torrents t
                    INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                    WHERE t.Time > now() - INTERVAL 3 DAY
                        AND t.Encoding IN ('Lossless', '24bit Lossless')
                        AND tg.WikiImage != ''
                        AND NOT EXISTS (
                            SELECT 1
                            FROM torrents_tags ttex
                            WHERE t.GroupID = ttex.GroupID
                                AND ttex.TagID IN (" . placeholders(HOMEPAGE_TAG_IGNORE) . ")
                        )
                    GROUP BY t.GroupID
                ) R
                INNER JOIN torrents_group tg ON (tg.ID = R.groupId)
                INNER JOIN torrents_tags  tt USING (GroupID)
                INNER JOIN tags           tag ON (tag.ID = tt.TagID)
                INNER JOIN torrents       t   ON (t.ID = R.torrentId)
                INNER JOIN users_main     um  ON (um.ID = t.UserID)
                GROUP BY R.GroupID
                ORDER BY R.uploadDate DESC
                ", ...HOMEPAGE_TAG_IGNORE
            );
            $latest = [];
            while (count($latest) < $limit) {
                $row = $this->db->next_record(MYSQLI_ASSOC, false);
                if (!$row) {
                    break;
                }
                if (isset($latest[$row['groupId']])) {
                    continue;
                } else {
                    $paranoia = unserialize($row['paranoia']);
                    if (is_array($paranoia) && in_array('uploads', $paranoia)) {
                        continue;
                    }
                }
                $row['name'] = \Torrents::display_string($row['groupId'], \Torrents::DISPLAYSTRING_SHORT);
                $latest[$row['groupId']] = $row;
            }
            $this->cache->cache_value(self::CACHE_KEY_LATEST_UPLOADS . $limit, $latest, 86400);
        }
        return $latest;
    }

    /**
     * Flush the most recent uploads (e.g. a new lossless upload is made).
     *
     * Note: Since arbitrary N may have been cached, all uses of N latest
     * uploads must be flushed when invalidating, following a new upload.
     * grep is your friend. This also assumes that there is sufficient
     * activity to not have to worry about a very recent upload being
     * deleted for whatever reason. For a start, even if the list becomes
     * briefly out of date, the next upload will regenerate the list.
     *
     * @param int $limit
     */
    public function flushLatestUploads(int $limit) {
        $this->cache->delete_value(self::CACHE_KEY_LATEST_UPLOADS . $limit);
    }

    /**
     * Regenerate a torrent's file list from its meta data,
     * update the database record and clear relevant cache keys
     *
     * @param int torrentId
     * @return int number of files regenned
     */
    public function regenerateFilelist(int $torrentId): int {
        $qid = $this->db->get_query_id();
        $groupId = $this->db->scalar("
            SELECT t.GroupID FROM torrents AS t WHERE t.ID = ?
            ", $torrentId
        );
        $n = 0;
        if ($groupId) {
            $Tor = new \OrpheusNET\BencodeTorrent\BencodeTorrent;
            $Tor->decodeString($str = (new \Gazelle\File\Torrent())->get($torrentId));
            $TorData = $Tor->getData();
            ['total_size' => $TotalSize, 'files' => $FileList] = $Tor->getFileList();
            $TmpFileList = [];
            foreach ($FileList as $file) {
                $TmpFileList[] = $this->metaFilename($file['path'], $file['size']);
                ++$n;
            }
            $this->db->prepared_query("
                UPDATE torrents SET
                    Size = ?,
                    FilePath = ?,
                    FileList = ?
                WHERE ID = ?
                ", $TotalSize,
                    (isset($TorData['info']['files']) ? make_utf8($Tor->getName()) : ''),
                    implode("\n", $TmpFileList),
                $torrentId
            );
            $this->cache->delete_value("torrents_details_$groupId");
        }
        $this->db->set_query_id($qid);
        return $n;
    }

    /**
     * Set the source flag of the torrent as appropriate
     *
     * @param OrpheusNET\BencodeTorrent\BencodeTorrent torrent to be checked
     * @return bool flag was set (implies modified infohash)
     */
    public function setSourceFlag(\OrpheusNET\BencodeTorrent\BencodeTorrent $torrent): bool {
        $sourceFlag = $torrent->getSource();
        if ($sourceFlag === SOURCE) {
            return false;
        }

        $creationDate = $torrent->getCreationDate();
        if (!is_null($creationDate)) {
            if (is_null($sourceFlag) && $creationDate <= GRANDFATHER_NO_SOURCE) {
                return false;
            }
            elseif ($sourceFlag === GRANDFATHER_SOURCE && $creationDate <= GRANDFATHER_OLD_SOURCE) {
                return false;
            }
        }
        return $torrent->setSource(SOURCE);
    }

    public function peerlistTotal(int $torrentId) {
        $key = sprintf(self::CACHE_KEY_PEERLIST_TOTAL, $torrentId);
        if (($total = $this->cache->get_value($key)) === false) {
            // force flush the first page of results
            $this->cache->delete_value(sprintf(self::CACHE_KEY_PEERLIST_PAGE, $torrentId, 0));
            $total = $this->db->scalar("
                SELECT count(*)
                FROM xbt_files_users AS xfu
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                WHERE um.Visible = '1'
                    AND xfu.fid = ?
                ", $torrentId
            );
            $this->cache->cache_value($key, $total, 300);
        }
        return $total;
    }

    public function peerlistPage(int $torrentId, int $userId, int $limit, int $offset) {
        $key = sprintf(self::CACHE_KEY_PEERLIST_PAGE, $torrentId, $offset);
        if (($list = $this->cache->get_value($key)) === false) {
            // force flush the next page of results
            $this->cache->delete_value(sprintf(self::CACHE_KEY_PEERLIST_PAGE, $torrentId, $offset + $limit));
            $this->db->prepared_query("
                SELECT
                    xfu.active,
                    xfu.connectable,
                    xfu.remaining,
                    xfu.uploaded,
                    xfu.useragent,
                    xfu.ip           AS ipv4addr,
                    xfu.uid          AS user_id,
                    t.Size           AS size,
                    sx.name          AS seedbox
                FROM xbt_files_users AS xfu
                INNER JOIN users_main AS um ON (um.ID = xfu.uid)
                INNER JOIN torrents AS t ON (t.ID = xfu.fid)
                LEFT JOIN user_seedbox sx ON (xfu.ip = inet_ntoa(sx.ipaddr) AND xfu.useragent = sx.useragent AND xfu.uid = ?)
                WHERE um.Visible = '1'
                    AND xfu.fid = ?
                ORDER BY xfu.uid = ? DESC, xfu.uploaded DESC
                LIMIT ? OFFSET ?
                ", $userId, $torrentId, $userId, $limit, $offset
            );
            $list = $this->db->to_array(false, MYSQLI_ASSOC);
            $this->cache->cache_value($key, $list, 300);
        }
        return $list;
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

    protected function featuredAlbum(int $type): array {
        $key = sprintf(self::CACHE_KEY_FEATURED, $type);
        if (($featured = $this->cache->get_value($key)) === false) {
            $featured = $this->db->rowAssoc("
                SELECT fa.GroupID,
                    tg.Name,
                    tg.WikiImage,
                    fa.ThreadID,
                    fa.Title
                FROM featured_albums AS fa
                INNER JOIN torrents_group AS tg ON (tg.ID = fa.GroupID)
                WHERE Ended IS NULL AND type = ?
                ", $type
            );
            if (!is_null($featured)) {
                $featured['artist_name'] = \Artists::display_artists(\Artists::get_artist($featured['GroupID']), false, false);
                $featured['image']       = \ImageTools::process($featured['WikiImage'], true);
            }
            $this->cache->cache_value($key, $featured, 86400 * 7);
        }
        return $featured ?? [];
    }

    public function featuredAlbumAotm(): array {
        return $this->featuredAlbum(self::FEATURED_AOTM);
    }

    public function featuredAlbumShowcase(): array {
        return $this->featuredAlbum(self::FEATURED_SHOWCASE);
    }

    public function modifyLogscore(int $groupId, int $torrentId): int {
        $count = $this->db->scalar("
            SELECT count(*) FROM torrents_logs WHERE TorrentID = ?
            ", $torrentId
        );
        if (!$count) {
            $this->db->prepared_query("
                UPDATE torrents SET
                    HasLogDB = '0',
                    LogChecksum = '1',
                    LogScore = 0
                WHERE ID = ?
                ", $torrentId
            );
        } else {
            $this->db->prepared_query("
                UPDATE torrents AS t
                LEFT JOIN (
                    SELECT TorrentID,
                        min(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
                        min(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
                    FROM torrents_logs
                    WHERE TorrentID = ?
                    GROUP BY TorrentID
                ) AS tl ON (t.ID = tl.TorrentID)
                SET
                    t.LogScore    = tl.Score,
                    t.LogChecksum = tl.Checksum
                WHERE t.ID = ?
                ", $torrentId, $torrentId
            );
        }
        $this->cache->deleteMulti(["torrent_group_{$groupId}", "torrents_details_{$groupId}"]);
        return $this->db->affected_rows();
    }

    public function adjustLogscore(int $groupId, int $torrentId, int $logId, $Adjusted, int $adjScore, $adjChecksum, $adjBy, $adjReason, $adjDetails): int {
        $this->db->prepared_query("
            UPDATE torrents_logs SET
                Adjusted = ?, AdjustedScore = ?, AdjustedChecksum = ?, AdjustedBy = ?, AdjustmentReason = ?, AdjustmentDetails = ?
            WHERE LogID = ? AND TorrentID = ?
            ", $Adjusted, $adjScore, $adjChecksum, $adjBy, $adjReason, $adjDetails,
                $logId, $torrentId
        );
        if ($this->db->affected_rows() > 0) {
            return $this->modifyLogscore($groupId, $torrentId);
        }
        return 0;
    }

    public function rescoreLog(int $groupId, int $torrentId, int $logId, \Gazelle\Logfile $logfile, string $version): int {
        $this->db->prepared_query("
            UPDATE torrents_logs SET
                Score = ?, `Checksum` = ?, ChecksumState = ?, Ripper = ?, RipperVersion = ?,
                `Language` = ?, Details = ?, LogcheckerVersion = ?,
                Adjusted = '0'
            WHERE LogID = ? AND TorrentID = ?
            ", $logfile->score(), $logfile->checksumStatus(), $logfile->checksumState(), $logfile->ripper(), $logfile->ripperVersion(),
                $logfile->language(), $logfile->detailsAsString(), $version,
                $logId, $torrentId
        );
        if ($this->db->affected_rows() > 0) {
            return $this->modifyLogscore($groupId, $torrentId);
        }
        return 0;
    }

    /**
     * Combine torrent media into a standardized file name
     *
     * @param array Torrent metadata
     * @param bool whether to use .txt or .torrent as file extension
     * @param int $MaxLength maximum file name length
     * @return string file name with at most $MaxLength characters
     */
    public function torrentFilename(array $info, bool $asText, $MaxLength = MAX_PATH_LEN) {
        $MaxLength -= ($asText ? 4 : 8);
        if ($info['TorrentID'] !== false) {
            $MaxLength -= (strlen($info['TorrentID']) + 1);
        }
        $artist = safeFilename($info['Artist']);
        if ($info['Year'] > 0) {
            $artist .= ".{$info['Year']}";
        }
        $meta = [];
        if ($info['Media'] != '') {
            $meta[] = $info['Media'];
        }
        if ($info['Format'] != '') {
            $meta[] = $info['Format'];
        }
        if ($info['Encoding'] != '') {
            $meta[] = $info['Encoding'];
        }
        $label = empty($meta) ? '' : ('.(' . safeFilename(implode('-', $meta)) . ')');

        $filename = safeFilename($info['Name']);
        if (!$filename) {
            $filename = 'Unnamed';
        } elseif (mb_strlen("$artist.$filename$label", 'UTF-8') <= $MaxLength) {
            $filename = "$artist.$filename";
        }

        $filename = shortenString($filename . $label, $MaxLength, true, false);
        if ($info['TorrentID'] !== false) {
            $filename .= "-{$info['TorrentID']}";
        }
        return $asText ? "$filename.txt" : "$filename.torrent";
    }

    /**
     * Convert a stored torrent into a binary file that can be loaded in a torrent client
     *
     * @param mixed $TorrentData bencoded torrent without announce URL
     * @param string $AnnounceURL
     * @param int $TorrentID
     * @return string bencoded string
     */
    public function torrentBody(int $TorrentID, string $AnnounceURL): string {
        $filer = new \Gazelle\File\Torrent;
        $contents = $filer->get($TorrentID);
        if (is_null($contents)) {
            return '';
        }
        $Tor = new \OrpheusNET\BencodeTorrent\BencodeTorrent();
        $Tor->decodeString($contents);
        $Tor->cleanDataDictionary();
        $Tor->setValue([
            'announce' => $AnnounceURL,
            'comment' => SITE_URL . "/torrents.php?torrentid=$TorrentID",
        ]);
        return $Tor->getEncode();
    }

    /**
     * Create a string that contains file info in a format that's easy to use for Sphinx
     *
     * @param  string  $Name file path
     * @param  int  $Size file size
     * @return string with the format .EXT sSIZEs NAME DELIMITER
     */
    public function metaFilename(string $name, int $size): string {
        $name = make_utf8(strtr($name, "\n\r\t", '   '));
        $extPos = mb_strrpos($name, '.');
        $ext = $extPos === false ? '' : trim(mb_substr($name, $extPos + 1));
        return sprintf(".%s s%ds %s %s", $ext, $size, $name, self::FILELIST_DELIM_UTF8);
    }

    /**
     *  a meta filename into a more useful array structure
     *
     * @param string meta filename formatted as ".EXT sSIZEs NAME DELIMITER"
     * @return with the keys 'ext', 'size' and 'name'
     */
    public function splitMetaFilename(string $metaname): array {
        preg_match('/^(\.\S+) s(\d+)s (.+) &divide;$/', $metaname, $match);
        return [
            'ext'  => $match[1] ?? null,
            'size' => (int)$match[2] ?? 0,
            // transform leading blanks into hard blanks so that it shows up in HTML
            'name' => preg_replace_callback('/^(\s+)/', function ($s) { return str_repeat('&nbsp;', strlen($s[1])); }, $match[3] ?? ''),
        ];
    }

    /**
     * Create a string that contains file info in the old format for the API
     *
     * @param string $File string with the format .EXT sSIZEs NAME DELIMITER
     * @return string with the format NAME{{{SIZE}}}
     */
    public function apiFilename(string $metaname): string {
        $info = $this->splitMetaFilename($metaname);
        return $info['name'] . '{{{' . $info['size'] . '}}}';
    }

// Count the number of audio files in a torrent file list per audio type
    /**
     * Aggregate the audio files per audio type
     *
     * @param string filelist
     * @return array of array of [ac3, flac, m4a, mp3] => count
     */
    function audioMap(string $fileList): array {
        $map = [];
        foreach (explode("\n", strtolower($fileList)) as $file) {
            $info = $this->splitMetaFilename($file);
            if (is_null($info['ext'])) {
                continue;
            }
            $ext = substr($info['ext'], 1); // skip over period
            if (in_array($ext, ['ac3', 'flac', 'm4a', 'mp3'])) {
                if (!isset($map[$ext])) {
                    $map[$ext] = 0;
                }
                ++$map[$ext];
            }
        }
        return $map;
    }
}
