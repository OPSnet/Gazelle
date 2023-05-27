<?php

namespace Gazelle\Collector;

class TList extends \Gazelle\Collector {
    protected array $ids;
    protected bool $all = false;

    public function setList(array $ids): TList {
        $this->ids = $ids;
        return $this;
    }

    public function prepare(array $list): bool {
        $this->sql = $this->queryPreamble($list) . "
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID) /* FIXME: only needed if sorting by Seeders */
            INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID AND tg.CategoryID = 1)
            WHERE t.ID IN (" . placeholders($this->ids) . ")
            ORDER BY t.GroupID ASC, sequence DESC, " .  self::ORDER_BY[$this->orderBy];
        $this->qid = self::$db->prepared_query($this->sql, ...$this->ids);
        return self::$db->has_results();
    }

    public function fillZip(\ZipStream\ZipStream $zip): void {
        while (($downloadList = $this->process('TorrentID')) != null) {
            foreach ($downloadList as $download) {
                $torrent = $this->torMan->findById($download['TorrentID']);
                if (is_null($torrent)) {
                    continue;
                }
                $download['Artist'] = $torrent->group()->artistRole()?->text();
                $this->addZip($zip, $download);
            }
        }
    }
}
