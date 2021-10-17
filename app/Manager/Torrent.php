<?php

namespace Gazelle\Manager;

use Gazelle\Exception\TorrentManagerIdNotSetException;
use Gazelle\Exception\TorrentManagerUserNotSetException;

class Torrent extends \Gazelle\Base {
    /*
     **** To display a torrent name, edition and flags, at the minimum the code looks like:

        $labelMan = new Gazelle\Manager\TorrentLabel;

        // set up the labeler once
        $labelMan->showMedia(true)->showEdition(true);
        $torrent = new Gazelle\Torrent(1666);

        // the artist name (A, A & B, Various Artists, Various Composers under Various Conductors etc)
        echo $torrent->group()->artistHtml();

        // load the torrent details into the labeler
        $labelMan->load($torrent->info());

        // remaster info, year, etc
        echo $labelMan->edition();

        // flags (Reported, Freeleech, Lossy WEB Approved, etc
        echo $labelMan->label();

    **** This is a bit cumbersome and subject to change
    */

    protected const ID_KEY = 'zz_t_%d';

    const FEATURED_AOTM     = 0;
    const FEATURED_SHOWCASE = 1;

    const CACHE_KEY_LATEST_UPLOADS = 'latest_uploads_';
    const CACHE_KEY_PEERLIST_TOTAL = 'peerlist_total_%d';
    const CACHE_KEY_PEERLIST_PAGE  = 'peerlist_page_%d_%d';
    const CACHE_KEY_FEATURED       = 'featured_%d';
    const CACHE_FOLDERNAME         = 'foldername_%s';
    const CACHE_REPORTLIST         = 'reports_torrent_%d';

    const FILELIST_DELIM_UTF8 = "\xC3\xB7";

    const SNATCHED_UPDATE_INTERVAL = 3600; // How often we want to update users' snatch lists
    const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    const ARTIST_DISPLAY_TEXT = 1;
    const ARTIST_DISPLAY_HTML = 2;

    protected int $viewerId;

    /**
     * Set the viewer context, for snatched indicators etc.
     * If this is set, and Torrent object created will have it set
     */
    public function setViewerId(int $viewerId) {
        $this->viewerId = $viewerId;
        return $this;
    }

    public function findById(int $torrentId): ?\Gazelle\Torrent {
        $key = sprintf(self::ID_KEY, $torrentId);
        $id = $this->cache->get_value($key);
        if ($id === false) {
            $id = $this->db->scalar("
                SELECT ID FROM torrents WHERE ID = ?
                ", $torrentId
            );
            if (!is_null($id)) {
                $this->cache->cache_value($key, $id, 0);
            }
        }
        if (!$id) {
            return null;
        }
        $torrent = new \Gazelle\Torrent($id);
        if (isset($this->viewerId)) {
            $torrent->setViewerId($this->viewerId);
        }
        return $torrent;
    }

    public function findByInfohash(string $hash) {
        return $this->findById((int)$this->db->scalar("
            SELECT id FROM torrents WHERE info_hash = unhex(?)
            ", $hash
        ));
    }

    /**
     * How many other uploads share the same folder path?
     *
     * @param string base path in the torrent
     * @return array of Gazelle\Torrent objects;
     */
    public function findAllByFoldername(string $folder): array {
        $key = sprintf(self::CACHE_FOLDERNAME, md5($folder));
        $list = $this->cache->get_value($key);
        if ($list === false) {
            $this->db->prepared_query("
                SELECT ID FROM torrents WHERE FilePath = ?
                ", $folder
            );
            $list = $this->db->collect(0);
            $this->cache->cache_value($key, $list, 0);
        }
        $all = [];
        foreach ($list as $id) {
            $torrent = $this->findById($id);
            if ($torrent) {
                $all[] = $torrent;
            }
        }
        return $all;
    }

    public function flushFoldernameCache(string $folder) {
        $this->cache->delete_value(sprintf(self::CACHE_FOLDERNAME, md5($folder)));
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
        preg_match('/^(\.\S+) s(\d+)s (.+) (?:&divide;|' . self::FILELIST_DELIM_UTF8 . ')$/', $metaname, $match);
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
            $folderPath = isset($TorData['info']['files']) ? make_utf8($Tor->getName()) : '';
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
                ", $TotalSize, $folderPath, implode("\n", $TmpFileList),
                $torrentId
            );
            $this->cache->delete_value("torrents_details_$groupId");
            $this->flushFoldernameCache($folderPath);
        }
        $this->db->set_query_id($qid);
        return $n;
    }

    public function setSourceFlag(\OrpheusNET\BencodeTorrent\BencodeTorrent $torrent) {
        $torrentSource = $torrent->getSource();
        if ($torrentSource === SOURCE) {
            return false;
        }
        $creationDate = $torrent->getCreationDate();
        if (!is_null($creationDate)) {
            if (is_null($torrentSource) && $creationDate <= GRANDFATHER_OLD_SOURCE) {
                return false;
            }
            elseif (!is_null($torrentSource) && $torrentSource === GRANDFATHER_SOURCE && $creationDate <= GRANDFATHER_OLD_SOURCE) {
                return false;
            }
        }
        return $torrent->setSource(SOURCE);
    }

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

    /**
     * Get the reports associated with a torrent
     * Non-admin users do not see Edited reports
     *
     * @param int torrent id
     * @return array of array of [ID, ReporterID, Type, UserComment, ReportedTime]
     */
    public function reportList(int $torrentId): array {
        $key = sprintf(self::CACHE_REPORTLIST, $torrentId);
        $list = $this->cache->get_value($key);
        if ($list === false) {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT ID,
                    ReporterID,
                    Type,
                    UserComment,
                    ReportedTime
                FROM reportsv2
                WHERE TorrentID = ?
                    AND Status != 'Resolved'",
                $torrentId
            );
            $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
            $this->db->set_query_id($qid);
            $this->cache->cache_value($key, $list, 0);
        }
        return check_perms('admin_reports')
            ? $list
            : array_filter($list, function ($report) { return $report['Type'] !== 'edited'; });
    }

    /**
     * Are there any reports associated with this torrent?
     *
     * @param int torrent id
     * @return bool Yes there are
     */
    public function hasReport(int $torrentId): bool {
        return count($this->reportList($torrentId)) > 0;
    }

    /**
     * Record who's seeding how much, used for ratio watch
     */
    public function updateSeedingHistory(): array {
        $this->db->prepared_query("
            CREATE TEMPORARY TABLE tmp_users_torrent_history (
                UserID int(10) unsigned NOT NULL PRIMARY KEY,
                NumTorrents int(6) unsigned NOT NULL DEFAULT 0,
                SumTime bigint(20) unsigned NOT NULL DEFAULT 0,
                SeedingAvg int(6) unsigned NOT NULL DEFAULT 0,
                KEY numtorrents_idx (NumTorrents)
            ) ENGINE=InnoDB
        ");

        // Find seeders that have announced within the last hour
        $this->db->prepared_query("
            INSERT INTO tmp_users_torrent_history
                (UserID, NumTorrents)
            SELECT uid, COUNT(DISTINCT fid)
            FROM xbt_files_users
            WHERE mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND Remaining = 0
            GROUP BY uid
        ");
        $info = ['new' => $this->db->affected_rows()];

        // Mark new records as "checked" and set the current time as the time
        // the user started seeding <NumTorrents> seeded.
        // Finished = 1 means that the user hasn't been seeding exactly <NumTorrents> earlier today.
        // This query will only do something if the next one inserted new rows last hour.
        $this->db->prepared_query("
            UPDATE users_torrent_history AS h
            INNER JOIN tmp_users_torrent_history AS t ON (t.UserID = h.UserID AND t.NumTorrents = h.NumTorrents)
            SET h.Finished = '0',
                h.LastTime = UNIX_TIMESTAMP(now())
            WHERE h.Finished = '1'
                AND h.Date = UTC_DATE() + 0
        ");
        $info['updated'] = $this->db->affected_rows();

        // Insert new rows for users who haven't been seeding exactly <NumTorrents> torrents earlier today
        // and update the time spent seeding <NumTorrents> torrents for the others.
        // Primary table index: (UserID, NumTorrents, Date).
        $this->db->prepared_query("
            INSERT INTO users_torrent_history
                (UserID, NumTorrents, Date)
            SELECT UserID, NumTorrents, UTC_DATE() + 0
            FROM tmp_users_torrent_history
            ON DUPLICATE KEY UPDATE
                Time = Time + UNIX_TIMESTAMP(now()) - LastTime,
                LastTime = UNIX_TIMESTAMP(now())
        ");
        $info['history'] = $this->db->affected_rows();

        return $info;
    }

    public function storeTop10(string $type, string $key, int $days) {
        $this->db->prepared_query("
            INSERT INTO top10_history (Type) VALUES (?)
            ", $type
        );
        $historyID = $this->db->inserted_id();

        $top10 = $this->cache->get_value('top10tor_v2_' . $key . '_10');
        if ($top10 === false) {
            $this->db->prepared_query("
                SELECT t.ID,
                    tg.ID,
                    (t.Size * tls.Snatched) + (t.Size * 0.5 * tls.Leechers) AS Data
                FROM torrents AS tg
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                INNER JOIN torrents_group AS g      ON (tg.ID = t.GroupID)
                WHERE tls.Seeders > 0
                    AND t.Time > now() - INTERVAL ? DAY
                GROUP BY tls.Seeders + tls.Leechers DESC
                ORDER BY t.ID, tg.ID
                LIMIT ?
                ", $days, 10
            );
            $top10 = $this->db->to_array(MYSQLI_NUM, false);
        }

        $groupIds = array_column($top10, 1);
        // exclude artists because it's retarded
        $groups = \Torrents::get_groups($groupIds, true, false);
        $artists = \Artists::get_artists($groupIds);
        global $Debug;
        foreach ($top10 as $i => $torrent) {
            [$torrentID, $groupID, $data] = $torrent;
            $group = $groups[$groupID];

            $displayName = '';

            if (!empty($artists[$groupID])) {
                $displayName = \Artists::display_artists($artists[$groupID], false, true);
            }

            $displayName .= $group['Name'];

            if ($group['CategoryID'] == 1 && $group['Year'] > 0) {
                $displayName .= " [${group['Year']}]";
            }

            $torrentDetails = $group['Torrents'][$torrentID];
            // some flags are less flaggy than other flags
            unset($torrentDetails['IsSnatched']);
            unset($torrentDetails['FreeTorrent']);
            unset($torrentDetails['PersonalFL']);
            //                                                    media, edition, flags
            $extraInfo = \Torrents::torrent_info($torrentDetails, true,  true,    false);
            if ($extraInfo != '') {
                $extraInfo = "- [$extraInfo]";
            }

            $titleString = "$displayName $extraInfo";

            $Debug->log_var($group, 'group');
            $this->db->prepared_query('
                INSERT INTO top10_history_torrents
                    (HistoryID, Rank, TorrentID, TitleString, TagString)
                VALUES
                    (?,         ?,    ?,         ?,           ?)
                ', $historyID, $i, $torrentID, $titleString, $group['TagList']
            );
        }
    }
}
