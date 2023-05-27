<?php

namespace Gazelle\Collector;

class Collage extends \Gazelle\Collector {
    public function __construct(
        protected \Gazelle\User            $user,
        protected \Gazelle\Manager\Torrent $torMan,
        protected \Gazelle\Collage         $collage,
        protected int                      $orderBy,
    ) {
        parent::__construct($user, $torMan, $collage->name(), $orderBy);
    }

    public function prepare(array $list): bool {
        $this->sql = $this->queryPreamble($list) . "
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID) /* FIXME: only needed if sorting by Seeders */
            INNER JOIN collages_torrents AS c ON (t.GroupID = c.GroupID AND c.CollageID = ?)
            INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID AND tg.CategoryID = 1)
            ORDER BY t.GroupID ASC, sequence DESC, " .  self::ORDER_BY[$this->orderBy];
        $this->qid = self::$db->prepared_query($this->sql, $this->collage->id());
        return self::$db->has_results();
    }

    public function fillZip(\ZipStream\ZipStream $zip): void {
        while (($downloadList = $this->process('GroupID')) != null) {
            foreach ($downloadList as $download) {
                $torrent = $this->torMan->findById($download['TorrentID']);
                if (is_null($torrent)) {
                    continue;
                }
                $info =& $downloadList[$torrent->groupId()];
                $info['Artist'] = $torrent->group()->artistRole()?->text();
                $this->addZip($zip, $info);
            }
        }
    }
}
