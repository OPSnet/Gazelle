<?php

namespace Gazelle\Stats;

class Artist extends \Gazelle\BaseObject {
    final const tableName     = '/* artist stats */';
    protected const CACHE_KEY = 'a_stats_%d';

    public function flush(): Artist {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">artist %d</a>', $this->url(), $this->id()); }
    public function location(): string { return 'artist.php?id=' . $this->id; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info =self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT count(*)                    AS torrent_total,
                    count(DISTINCT tg.ID)          AS tgroup_total,
                    coalesce(sum(tls.Leechers), 0) AS leecher_total,
                    coalesce(sum(tls.Seeders), 0)  AS seeder_total,
                    coalesce(sum(tls.Snatched), 0) AS snatch_total
                FROM torrents_artists           ta
                INNER JOIN torrents_group       tg  ON (tg.ID = ta.GroupID)
                INNER JOIN torrents             t   ON (t.GroupID = tg.ID)
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
                WHERE ta.ArtistID = ?
                ", $this->id()
            );
            self::$cache->cache_value($key, $info, 3600);
        }
        $this->info = $info;
        return $this->info;
    }

    public function leecherTotal(): int {
        return $this->info()['leecher_total'];
    }

    public function seederTotal(): int {
        return $this->info()['seeder_total'];
    }

    public function snatchTotal(): int {
        return $this->info()['snatch_total'];
    }

    public function tgroupTotal(): int {
        return $this->info()['tgroup_total'];
    }

    public function torrentTotal(): int {
        return $this->info()['torrent_total'];
    }
}
