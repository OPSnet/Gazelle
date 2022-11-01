<?php

namespace Gazelle\Search;

class Transcode extends \Gazelle\Base {
    /**
     * A class to deal with finding Lossless uploads that can be transcoded,
     * based on what a user is seeding or has snatched.
     *
     * A FLAC torrent can be transcoded to MP3 V0 or MP3 320, so the idea is to
     * search for snatched or seeding FLACs and see whether the correponding V0
     * or 320 exist. Of note: there is no need to GROUP BY t.Encoding, which
     * sidesteps the issue of whether the Lossless, the 24bit Lossless or both
     * encodes have been snatched/seeding.
     *
     * The complexity of the query comes from the fact that remasters are not
     * first class citizens in the database (and if they were, this, and a
     * number of other things, like display, would become much easier).
     *
     * Another difficulty is that some people have 500k+ seeds or snatches, and
     * to calculate the complete results takes too long to be useful. To avoid
     * this, we cheat and take a subset of all their seeds/snatches. If they
     * never do any transcoding, the result set will not change much over time
     * so there is no need to be overly worried about this shortcut.
     */

    protected const SEEDING_KEY  = 'u_better_seed_%d';
    protected const SNATCHED_KEY = 'u_better_snatch_%d';
    protected const WANT_KEY     = 'u_better_want_%s_%d';

    protected const MODE_SEEDING  = 1;
    protected const MODE_SNATCHED = 2;
    protected const MODE_UPLOADED = 3;
    protected int $mode = self::MODE_UPLOADED;

    protected bool $wantV0  = true;
    protected bool $want320 = true;
    protected string $search;

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\Torrent $torMan = new \Gazelle\Manager\Torrent
    ) {}

    public function setModeSeeding() {
        $this->mode = self::MODE_SEEDING;
    }

    public function setModeSnatched() {
        $this->mode = self::MODE_SNATCHED;
        return $this;
    }

    public function setModeUploaded() {
        $this->mode = self::MODE_UPLOADED;
        return $this;
    }

    public function setSearch(string $search) {
        $this->search = $search;
        return $this;
    }

    public function wantV0(bool $want) {
        $this->wantV0 = $want;
        return $this;
    }

    public function want320(bool $want) {
        $this->want320 = $want;
        return $this;
    }

    protected function direction(): string {
        return ['ASC', 'DESC'][rand() % 2];
        return $this;
    }

    protected function seedingList(): array {
        $key = sprintf(self::SEEDING_KEY, $this->user->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $orderBy = $this->direction();
            self::$db->prepared_query("
                SELECT DISTINCT fid
                FROM xbt_files_users
                WHERE active = 1
                    AND remaining = 0
                    AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR)
                    AND uid = ?
                ORDER BY fid $orderBy LIMIT 1000
                ", $this->user->id()
            );
            $list = self::$db->collect(0, false);
            self::$cache->cache_value($key, $list, 86400);
        }
        return $list;
    }

    protected function snatchedList(): array {
        $key = sprintf(self::SNATCHED_KEY, $this->user->id());
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $orderBy = $this->direction();
            self::$db->prepared_query("
                SELECT DISTINCT fid FROM xbt_snatched WHERE uid = ?  ORDER BY fid $orderBy LIMIT 1000
                ", $this->user->id()
            );
            $list = self::$db->collect(0, false);
            self::$cache->cache_value($key, $list, 86400);
        }
        return $list;
    }

    protected function uploadedList(): array {
        self::$db->prepared_query("
            SELECT ID
            FROM torrents
            WHERE Format = 'FLAC'
                AND UserID = ?
            ", $this->user->id()
        );
        return self::$db->collect(0, false);
    }

    public function joinV0(): array {
        return [
            'column' => "t_v0.ID  AS mp3_v0",
            'cond'   => "t_v0.ID IS NULL",
            'join'   => "
                LEFT JOIN torrents t_v0 on (t_v0.GroupID = t.GroupID
                    AND t_v0.Remastered              = t.Remastered
                    AND t_v0.RemasterYear            = t.RemasterYear
                    AND t_v0.RemasterTitle           = t.RemasterTitle
                    AND t_v0.RemasterRecordLabel     = t.RemasterRecordLabel
                    AND t_v0.RemasterCatalogueNumber = t.RemasterCatalogueNumber
                    AND t_v0.Media                   = t.Media
                    AND t_v0.Format                  = 'MP3'
                    AND t_v0.Encoding                = 'V0 (VBR)'
                )",
        ];
    }

    public function join320(): array {
        return [
            'column' => "t_320.ID  AS mp3_320",
            'cond'   => "t_320.ID IS NULL",
            'join'   => "
                LEFT JOIN torrents t_320 on (t_320.GroupID = t.GroupID
                    AND t_320.Remastered              = t.Remastered
                    AND t_320.RemasterYear            = t.RemasterYear
                    AND t_320.RemasterTitle           = t.RemasterTitle
                    AND t_320.RemasterRecordLabel     = t.RemasterRecordLabel
                    AND t_320.RemasterCatalogueNumber = t.RemasterCatalogueNumber
                    AND t_320.Media                   = t.Media
                    AND t_320.Format                  = 'MP3'
                    AND t_320.Encoding                = '320'
                )",
        ];
    }

    public function list(): array {
        $idList = match($this->mode) {
            self::MODE_SEEDING  => $this->seedingList(),
            self::MODE_SNATCHED => $this->snatchedList(),
            self::MODE_UPLOADED => $this->uploadedList(),
        };
        if (!$idList) {
            return [];
        }
        $key = sprintf(self::WANT_KEY,
            ($this->mode == self::MODE_SNATCHED ? 'snatch' : 'seed')
                . ($this->wantV0 ? '_v0' : '')
                . ($this->want320 ? '_320' : ''),
            $this->user->id()
        );
        $list = self::$cache->get_value($key);
        if (isset($this->search) || $list === false) {
            $column = [];
            $cond   = [];
            $join   = [];
            if ($this->wantV0) {
                $want = $this->joinV0();
                $column[] = $want['column'];
                $cond[]   = $want['cond'];
                $join[]   = $want['join'];
            }
            if ($this->want320) {
                $want = $this->join320();
                $column[] = $want['column'];
                $cond[]   = $want['cond'];
                $join[]   = $want['join'];
            }
            $sql = "SELECT t.ID  AS source, " . implode(', ', $column)
                . " FROM torrents t INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)"
                . implode("\n", $join)
                . " WHERE t.Format = 'FLAC'
                    AND tg.CategoryID = 1
                    AND (" . implode(' OR ', $cond) . ")
                    AND t.ID IN (" . placeholders($idList) . ")";

            $args = $idList;
            if (isset($this->search)) {
                $sql .= " AND tg.Name LIKE concat('%', ?, '%')";
                $args[] = $this->search;
            }

            $sql .= " GROUP BY t.media, t.format, t.RemasterYear, t.RemasterTitle, t.RemasterRecordLabel, t.RemasterCatalogueNumber";
            self::$db->prepared_query($sql, ...$args);
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            if (!isset($this->search)) {
                self::$cache->cache_value($key, $list, 3600 * 3);
            }
        }
        foreach ($list as &$row) {
            $row['torrent'] = $this->torMan->findById($row['source']);
        }
        return $list;
    }
}
