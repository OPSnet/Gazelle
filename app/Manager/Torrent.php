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

    public function findById(int $torrentId) {
        $id = $this->db->scalar("
            SELECT ID FROM torrents WHERE ID = ?
            ", $torrentId
        );
        return $id ? new \Gazelle\Torrent($id) : null;
    }

    public function findByInfohash(string $hash) {
        $id = $this->db->scalar("
            SELECT ID FROM torrents WHERE info_hash = UNHEX(?)
            ", $hash
        );
        return $id ? new \Gazelle\Torrent($id) : null;
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
}
