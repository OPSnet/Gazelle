<?php

namespace Gazelle\Collector;

class Collage extends \Gazelle\Collector {

    protected $collage;

    public function __construct(\Gazelle\User $user, \Gazelle\Collage $collage, int $orderBy) {
        parent::__construct($user, $collage->name(), $orderBy);
        $this->collage = $collage;
    }

    public function prepare(array $list): bool {
        $this->sql = $this->queryPreamble($list) . "
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID) /* FIXME: only needed if sorting by Seeders */
            INNER JOIN collages_torrents AS c ON (t.GroupID = c.GroupID AND c.CollageID = ?)
            INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID AND tg.CategoryID = '1')
            ORDER BY t.GroupID ASC, sequence DESC, " .  self::ORDER_BY[$this->orderBy];
        $this->qid = self::$db->prepared_query($this->sql, $this->collage->id());
        return self::$db->has_results();
    }

    public function fill() {
        while ([$Downloads, $GroupIDs] = $this->process('GroupID')) {
            if (is_null($Downloads)) {
                break;
            }
            $Artists = \Artists::get_artists($GroupIDs);
            self::$db->prepared_query("
                SELECT ID FROM torrents WHERE GroupID IN (" . placeholders($GroupIDs) .  ")
                ", ...$GroupIDs
            );
            $torrentIds = self::$db->collect('ID');
            foreach ($torrentIds as $TorrentID) {
                if (!isset($GroupIDs[$TorrentID])) {
                    continue;
                }
                $info =& $Downloads[$GroupIDs[$TorrentID]];
                $info['Artist'] = \Artists::display_artists($Artists[$info['GroupID']], false, false, false);
                $this->add($info);
            }
        }
    }
}
