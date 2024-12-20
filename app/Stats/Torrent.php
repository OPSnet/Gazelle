<?php

namespace Gazelle\Stats;

class Torrent extends \Gazelle\Base {
    protected array $info;

    final public const CACHE_KEY      = 'stat_global_torrent';
    final public const PEER_KEY       = 'stat_global_peer';
    final public const TORRENT_FLOW   = 'stat_t_flow';
    final public const CATEGORY_TOTAL = 'stat_tcat';

    public function flush(): static {
        self::$cache->deleteMulti([
            self::CACHE_KEY,
            self::PEER_KEY,
            self::TORRENT_FLOW,
            self::CATEGORY_TOTAL,
        ]);
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (!isset($this->info)) {
            $info = self::$cache->get_value(self::CACHE_KEY);
            if ($info === false) {
                $info = [
                    'day'       => [],
                    'week'      => [],
                    'month'     => [],
                    'quarter'   => [],
                ];

                [$info['torrent-total'], $info['total-size'], $info['total-files']] = self::$db->row("
                    SELECT count(*),
                        coalesce(sum(Size), 0),
                        coalesce(sum(FileCount), 0)
                    FROM torrents
                ");

                [$info['day']['count'], $info['day']['size'], $info['day']['files']] = self::$db->row("
                    SELECT count(*),
                        coalesce(sum(Size), 0),
                        coalesce(sum(FileCount), 0)
                    FROM torrents
                    WHERE created > now() - INTERVAL 1 DAY
                ");

                [$info['week']['count'], $info['week']['size'], $info['week']['files']] = self::$db->row("
                    SELECT count(*),
                        coalesce(sum(Size), 0),
                        coalesce(sum(FileCount), 0)
                    FROM torrents
                    WHERE created > now() - INTERVAL 7 DAY
                ");

                [$info['month']['count'], $info['month']['size'], $info['month']['files']] = self::$db->row("
                    SELECT count(*),
                        coalesce(sum(Size), 0),
                        coalesce(sum(FileCount), 0)
                    FROM torrents
                    WHERE created > now() - INTERVAL 30 DAY
                ");

                [$info['quarter']['count'], $info['quarter']['size'], $info['quarter']['files']] = self::$db->row('
                    SELECT count(*),
                        coalesce(sum(Size), 0),
                        coalesce(sum(FileCount), 0)
                    FROM torrents
                    WHERE created > now() - INTERVAL 120 DAY
                ');

                self::$db->prepared_query("
                    SELECT Format, Encoding, count(*) as n
                    FROM torrents
                    GROUP BY Format, Encoding WITH ROLLUP
                ");
                $info['format'] = self::$db->to_array(false, MYSQLI_NUM, false);

                self::$db->prepared_query("
                    SELECT Format, Encoding, count(*) as n
                    FROM torrents
                    WHERE created > now() - INTERVAL 1 MONTH
                    GROUP BY Format, Encoding WITH ROLLUP
                ");
                $info['format-month'] = self::$db->to_array(false, MYSQLI_NUM, false);

                self::$db->prepared_query("
                    SELECT t.Media, count(*) as n
                    FROM torrents t
                    GROUP BY t.Media WITH ROLLUP
                ");
                $info['media'] = self::$db->to_array(false, MYSQLI_NUM, false);

                self::$db->prepared_query("
                    SELECT tg.CategoryID, count(*) AS n
                    FROM torrents_group tg
                    WHERE EXISTS (
                        SELECT 1 FROM torrents t WHERE t.GroupID = tg.ID)
                    GROUP BY tg.CategoryID
                    ORDER BY 2 DESC
                ");
                $info['category'] = self::$db->to_array(false, MYSQLI_NUM, false);

                self::$cache->cache_value(self::CACHE_KEY, $info, 7200);
            }
            $this->info = $info;
        }
        return $this->info;
    }

    public function torrentTotal(): int           { return $this->info()['torrent-total']; }
    public function totalFiles(): int             { return $this->info()['total-files']; }
    public function totalSize(): int              { return $this->info()['total-size']; }
    public function amount(string $interval): int { return $this->info()[$interval]['count']; }
    public function files(string $interval): int  { return $this->info()[$interval]['files']; }
    public function size(string $interval): int   { return $this->info()[$interval]['size']; }
    public function category(): array             { return $this->info()['category']; }
    public function format(): array               { return $this->info()['format']; }
    public function formatMonth(): array          { return $this->info()['format-month']; }
    public function media(): array                { return $this->info()['media']; }

    /**
     * Yearly torrent flows (added, removed and net per month)
     */
    public function flow(): array {
        $flow = self::$cache->get_value(self::TORRENT_FLOW);
        if ($flow === false) {
            self::$db->prepared_query("
                WITH RECURSIVE dates AS (
                    SELECT last_day(now() - INTERVAL 24 MONTH) AS eom
                    UNION ALL
                    SELECT last_day(eom + INTERVAL 1 MONTH)
                    FROM dates
                    WHERE last_day(eom + INTERVAL 1 MONTH) < last_day(now())
                ),
                delta AS (
                    SELECT last_day(Time)                                         AS eom,
                        sum(if(Message LIKE 'Torrent % was uploaded by %', 1, 0)) AS t_add,
                        sum(if(Message LIKE 'Torrent % was deleted by %', -1, 0)) AS t_del
                    FROM log
                    WHERE Time
                        BETWEEN last_day(now() - INTERVAL 24 MONTH)
                        AND last_day(now() - INTERVAL 1 MONTH)
                    GROUP BY eom
                )
                SELECT date_format(dates.eom, '%Y-%m') AS Month,
                    count(DISTINCT t.ID)               AS t_net,
                    coalesce(delta.t_add, 0)           AS t_add,
                    coalesce(delta.t_del, 0)           AS t_del
                FROM dates
                LEFT JOIN torrents t ON (last_day(t.created) = dates.eom)
                LEFT JOIN delta USING (eom)
                GROUP BY eom
                ORDER BY eom
            ");
            $flow = self::$db->to_array('Month', MYSQLI_ASSOC, false);
            foreach ($flow as &$f) {
                $f['t_add'] = (int)$f['t_add'];
                $f['t_del'] = (int)$f['t_del'];
            }
            unset($f);
            self::$cache->cache_value(self::TORRENT_FLOW, $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for dec -> jan
        }
        return $flow;
    }

    /**
     * Get the totals by category
     */
    public function categoryList(): array {
        $list = self::$cache->get_value(self::CATEGORY_TOTAL);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT tg.CategoryID,
                    count(*) as total
                FROM torrents AS t
                INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
                GROUP BY tg.CategoryID
                ORDER BY 2 DESC
            ");
            $list = self::$db->to_pair('CategoryID', 'total', false);
            self::$cache->cache_value(self::CATEGORY_TOTAL, $list, mktime(0, 0, 0, date('n') + 1, 2));
        }
        return $list;
    }

    public function categoryTotal(): array {
        $list = [];
        foreach ($this->categoryList() as $cat => $value) {
            $list[] = [
                'name' => CATEGORY[$cat - 1],
                'y'    => $value,
            ];
        }
        return $list;
    }

    /**
     * Total number of albums (category 1 == Music) uploaded
     */
    public function albumTotal(): int {
        $total = self::$cache->get_value('stats_album_count');
        if ($total === false) {
            $total = (int)self::$db->scalar("
                SELECT count(*) FROM torrents_group WHERE CategoryID = 1
            ");
            self::$cache->cache_value('stats_album_count', $total, 7200 + random_int(0, 300));
        }
        return $total;
    }

    /**
     * Total number of artists
     */
    public function artistTotal(): int {
        $total = self::$cache->get_value('stats_artist_count');
        if ($total === false) {
            $total = (int)self::$db->scalar("
                SELECT count(*) FROM artists_group
            ");
            self::$cache->cache_value('stats_artist_count', $total, 7200 + random_int(0, 300));
        }
        return $total;
    }

    /**
     * Total number of perfect flacs uploaded
     */
    public function perfectFlacTotal(): int {
        $total = self::$cache->get_value('stats_perfect_total');
        if ($total === false) {
            $total = (int)self::$db->scalar("
                SELECT count(*)
                FROM torrents
                WHERE Format = 'FLAC'
                    AND (
                        (Media = 'CD' AND LogChecksum = '1' AND HasCue = '1' AND HasLogDB = '1' AND LogScore = 100)
                        OR
                        (Media in ('BD', 'DVD', 'Soundboard', 'WEB', 'Vinyl'))
                    )
            ");
            self::$cache->cache_value('stats_perfect_total', $total, 7200 + random_int(0, 300));
        }
        return $total;
    }
}
