<?php

namespace Gazelle\Collector;

class Artist extends \Gazelle\Collector {

    protected $artist;
    protected $roleList = [];

    public function __construct(\Gazelle\User $user, \Gazelle\Artist $artist, int $orderBy) {
        parent::__construct($user, $artist->name(), $orderBy);
        $this->artist = $artist;
    }

    public function prepare(array $list): bool {
        self::$db->prepared_query("
            SELECT GroupID, Importance
            FROM torrents_artists
            WHERE ArtistID = ?
            ", $this->artist->id()
        );
        while ([$groupId, $role] = self::$db->next_record(MYSQLI_NUM, false)) {
            // Get the highest importances to place the .torrents in the most relevant folders
            if (!isset($this->roleList[$groupId]) || $role < $this->roleList[$groupId]) {
                $this->roleList[$groupId] = (int)$role;
            }
        }
        $this->args = array_keys($this->roleList);
        $this->sql = $this->queryPreamble($list) . "
            FROM torrents AS t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID) /* FIXME: only needed if sorting by Seeders */
            INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID AND tg.CategoryID = '1' AND tg.ID IN (" . placeholders($this->args) . ")
            ORDER BY t.GroupID ASC, Rank DESC, " .  self::ORDER_BY[$this->orderBy];

        $this->qid = self::$db->prepared_query($this->sql, ...$this->args);
        return self::$db->has_results();
    }

    public function fill() {
        $filer = new \Gazelle\File\Torrent;
        $releaseMan = new \Gazelle\ReleaseType;
        while ([$Downloads, $GroupIDs] = $this->process('GroupID')) {
            if (is_null($Downloads)) {
                break;
            }
            $Artists = \Artists::get_artists($GroupIDs);
            self::$db->prepared_query("
                SELECT ID FROM torrents WHERE ID IN (" . placeholders($GroupIDs) . ")
                ", ...array_keys($GroupIDs)
            );
            $torrentIds = self::$db->collect('ID');
            foreach ($torrentIds as $TorrentID) {
                if (!isset($GroupIDs[$TorrentID])) {
                    continue;
                }
                $GroupID = $GroupIDs[$TorrentID];
                $info =& $Downloads[$GroupID];
                $info['Artist'] = \Artists::display_artists($Artists[$GroupID], false, false, false);
                switch ($this->roleList[$GroupID]) {
                    case ARTIST_MAIN:
                        $ReleaseTypeName = $releaseMan->findNameById($info['ReleaseType']);
                        break;
                    case ARTIST_GUEST:
                        $ReleaseTypeName = 'Guest Appearance';
                        break;
                    case ARTIST_REMIXER:
                        $ReleaseTypeName = 'Remixed By';
                        break;
                    case ARTIST_COMPOSER:
                        $ReleaseTypeName = 'Composition';
                        break;
                    case ARTIST_CONDUCTOR:
                        $ReleaseTypeName = 'Conducted By';
                        break;
                    case ARTIST_DJ:
                        $ReleaseTypeName = 'DJ Mix';
                        break;
                    case ARTIST_PRODUCER:
                        $ReleaseTypeName = 'Produced By';
                        break;
                    case ARTIST_ARRANGER:
                        $ReleaseTypeName = 'Arranged By';
                        break;
                    default:
                        $ReleaseTypeName = 'Other-' . $this->roleList[$GroupID];
                        break;
                }
                $this->add($info, $ReleaseTypeName);
            }
        }
    }
}
