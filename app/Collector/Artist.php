<?php

namespace Gazelle\Collector;

class Artist extends \Gazelle\Collector {
    protected array $roleList = [];

    public function __construct(
        protected \Gazelle\User            $user,
        protected \Gazelle\Manager\Torrent $torMan,
        protected \Gazelle\Artist          $artist,
        protected int                      $orderBy,
    ) {
        parent::__construct($user, $torMan, $artist->name(), $orderBy);
    }

    public function prepare(array $list): bool {
        self::$db->prepared_query("
            SELECT GroupID, artist_role_id
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
            INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID AND tg.CategoryID = 1 AND tg.ID IN (" . placeholders($this->args) . "))
            ORDER BY t.GroupID ASC, sequence DESC, " .  self::ORDER_BY[$this->orderBy];

        $this->qid = self::$db->prepared_query($this->sql, ...$this->args);
        return self::$db->has_results();
    }

    public function fillZip(\ZipStream\ZipStream $zip): void {
        $releaseMan = new \Gazelle\ReleaseType();
        while (($downloadList = $this->process('GroupID')) != null) {
            foreach ($downloadList as $download) {
                $torrent = $this->torMan->findById($download['TorrentID']);
                if (is_null($torrent)) {
                    continue;
                }
                $tgroup = $torrent->group();
                $info =& $downloadList[$tgroup->id()];
                $info['Artist'] = $tgroup->artistRole()?->text();
                $this->addZip(
                    $zip,
                    $info,
                    match ($this->roleList[$tgroup->id()]) {
                        ARTIST_MAIN      => $releaseMan->findNameById($info['ReleaseType']),
                        ARTIST_GUEST     => 'Guest Appearance',
                        ARTIST_REMIXER   => 'Remixed By',
                        ARTIST_COMPOSER  => 'Composition',
                        ARTIST_CONDUCTOR => 'Conducted By',
                        ARTIST_DJ        => 'DJ Mix',
                        ARTIST_PRODUCER  => 'Produced By',
                        ARTIST_ARRANGER  => 'Arranged By',
                        default          => 'Other-' . $this->roleList[$tgroup->id()],
                    }
                );
            }
        }
    }
}
