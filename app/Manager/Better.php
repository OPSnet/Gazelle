<?php

namespace Gazelle\Manager;

class Better extends \Gazelle\Base
{
    protected $releaseTypes;

    public function __construct(array $releaseTypes) {
        parent::__construct();
        $this->releaseTypes = $releaseTypes;
    }

    public function removeAttribute(string $type, int $id) {
        $table = $this->badMap[$type] ?? null;
        if (!$table) {
            return;
        }

        $this->db->prepared_query(sprintf('
            DELETE FROM %s
            WHERE TorrentID = ?
            ', $table), $id
        );

        $torrent = new \Gazelle\Torrent();
        $groupId = $torrent->idToGroup($id);
        $this->cache->delete_value('torrents_details_'.$groupId);
    }

    public function missing(string $type, string $filter, string $search, int $limit, int $offset, int $userId) {
        $baseQuery = '';
        $columns = '';
        $joins = [];
        $where = [];
        $order = '';
        $params = [];
        $joinParams = [];

        switch ($type) {
            case 'checksum':
                $columns = 't.ID AS TorrentID, t.GroupID';
                $baseQuery = '
                    FROM torrents t
                    INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
                    INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)';
                $order = 'ORDER BY tls.Snatched DESC, t.Time ASC';
                $where[] = "t.HasLogDB = '1' AND t.LogChecksum = '0'";
                $mode = 'torrents';
                switch ($filter) {
                    case 'snatched':
                        $joins[] = 'INNER JOIN xbt_snatched as x ON x.fid = t.ID AND x.uid = ?';
                        $joinParams[] = $userId;
                        break;
                    case 'uploaded':
                        $where[]  = 't.UserID = ?';
                        $params[] = $userId;
                        break;
                }
                break;
            case 'tags':
            case 'folders':
            case 'files':
            case 'lineage':
                $columns = 'bad.TorrentID, t.GroupID';
                $baseQuery = sprintf('
                    FROM %s AS bad
                    INNER JOIN torrents t ON (t.ID = bad.TorrentID)
                    INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)', $this->badMap[$type]);
                $order = 'ORDER BY bad.TimeAdded ASC';
                $mode = 'torrents';
                switch ($filter) {
                    case 'snatched':
                        $joins[] = 'INNER JOIN xbt_snatched x ON (x.fid = bad.TorrentID AND x.uid = ?)';
                        $joinParams[] = $userId;
                        break;
                    case 'uploaded':
                        $where[] = 't.UserID = ?';
                        $params[] = $userId;
                        break;
                }
                break;
            case 'artwork':
                $columns = 'tg.ID, tg.Name';
                $baseQuery = "
                    FROM torrents_group tg
                    LEFT JOIN wiki_torrents wt ON (wt.RevisionID = tg.RevisionID)
                    LEFT JOIN torrent_group_has_attr tgha ON (tgha.TorrentGroupID = tg.ID
                        AND tgha.TorrentGroupAttrID = (
                            SELECT tga.ID FROM torrent_group_attr tga WHERE tga.Name = 'no-cover-art'
                        )
                    )";
                $where[] = "tg.CategoryID = 1 AND coalesce(wt.Image, tg.WikiImage) = '' AND tgha.TorrentGroupID IS NULL";
                $order = 'ORDER BY tg.Name';
                switch ($filter) {
                    case 'snatched':
                        $joins[] = '
                            INNER JOIN
                            (
                                SELECT DISTINCT t.GroupID
                                FROM torrents t
                                INNER JOIN xbt_snatched x ON (x.fid = t.ID AND x.uid = ?)
                            ) s ON (s.GroupID = tg.ID)';
                        $joinParams[] = $userId;
                        break;
                    case 'uploaded':
                        $joins[] = '
                            INNER JOIN
                            (
                                SELECT DISTINCT GroupID
                                FROM torrents
                                WHERE UserID = ?
                            ) u ON (u.GroupID = tg.ID)';
                        $joinParams[] = $userId;
                        break;
                }
                $mode = 'groups';
                break;
            case 'artistimg':
                $where[] = "(wa.Image IS NULL OR wa.Image = '')";
                $mode = 'artists';
                break;
            case 'artistdesc':
                $where[] = "(wa.Body IS NULL OR wa.Body = '')";
                $mode = 'artists';
                break;
            case 'artistdiscogs':
                $joins[] = "LEFT JOIN artist_discogs dg ON (dg.artist_id = a.ArtistID)";
                $where[] = "(dg.artist_id IS NULL)";
                $mode = 'artists';
                break;
        }

        if ($mode === 'artists') {
            $columns = 'a.ArtistID, a.Name';
            $baseQuery = '
                FROM artists_group a
                LEFT JOIN wiki_artists wa ON (wa.RevisionID = a.RevisionID)
                LEFT JOIN artist_usage au ON (au.artist_id = a.ArtistID)';
            $order = 'ORDER BY coalesce(au.uses, 0) DESC, a.Name ASC';
        }

        if ($search !== '') {
            switch ($mode) {
                case 'torrents':
                    $where[] = '(
                            tg.Name LIKE ?
                        OR  t.Description LIKE ?
                        OR  coalesce(wt.Body, tg.WikiBody) LIKE ?
                        OR  tg.TagList LIKE ?
                    )';
                    $searchString = "%$search%";
                    $params = array_merge($params, array_fill(0, 4, $searchString));
                    $joins[] = 'LEFT JOIN wiki_torrents wt ON (wt.RevisionID = tg.RevisionID)';
                    break;
                case 'groups':
                    $where[] = '(
                            tg.Name LIKE ?
                        OR  coalesce(wt.Body, tg.WikiBody) LIKE ?
                        OR  tg.TagList LIKE ?
                    )';
                    $searchString = "%$search%";
                    $params = array_merge($params, array_fill(0, 3, $searchString));
                    break;
                case 'artists':
                    $where[] = 'a.Name LIKE ?';
                    $searchString = "%$search%";
                    $params[] = $searchString;
                    break;
            }
        }

        if (count($where) > 0) {
            $where = 'WHERE '.implode(' AND ', $where);
        } else {
            $where = '';
        }


        $joins = implode("\n", $joins);
        $params = array_merge($joinParams, $params);

        $query = sprintf('
            SELECT count(*)
            %s
            %s
            %s', $baseQuery, $joins, $where
        );
        $resultCount = $this->db->scalar($query, ...$params);

        $query = sprintf('
            SELECT %s
            %s
            %s
            %s
            %s
            LIMIT %s OFFSET %s', $columns, $baseQuery, $joins, $where, $order, $limit, $offset
        );

        $this->db->prepared_query($query, ...$params);

        switch ($mode) {
            case 'torrents':
                if ($resultCount > 0) {
                    $torrents = $this->db->to_array('TorrentID', MYSQLI_ASSOC);
                } else {
                    $torrents  = [];
                }
                $groups = \Torrents::get_groups(array_column($torrents, 'GroupID'));
                $results = array_map(function ($torrent) use ($groups) {
                    return ['ID' => $torrent['TorrentID'], 'Group' => $groups[$torrent['GroupID']]];
                }, $torrents);
                break;
            case 'groups':
                if ($resultCount > 0) {
                    $results = $this->db->to_array('ID', MYSQLI_ASSOC);
                    foreach (\Artists::get_artists(array_keys($results)) as $groupId => $data) {
                        $results[$groupId]['Artists'] = [];
                        $results[$groupId]['ExtendedArtists'] = [];
                        foreach ([1, 4, 6] as $importance) {
                            if (isset($data[$importance])) {
                                $results[$groupId]['Artists'] = array_merge($results[$groupId]['Artists'], $data[$importance]);
                            }
                        }
                    }
                } else {
                    $results  = [];
                }
                break;
            case 'artists':
                if ($resultCount > 0) {
                    $results = $this->db->to_array('ArtistID', MYSQLI_ASSOC);
                } else {
                    $results = [];
                }
                break;
        }

        return [$results, $resultCount, $mode];
    }

    public function singleSeeded() {
        $this->db->prepared_query("
            SELECT t.ID, t.GroupID
            FROM torrents t
            INNER JOIN torrents_leech_stats tls On (t.ID = tls.TorrentID)
            WHERE t.Format = 'FLAC'
              AND tls.Seeders = 1
              ORDER BY t.LogScore DESC, rand()
              LIMIT 50
            "
        );

        $torrents = $this->db->to_array('ID', MYSQLI_ASSOC);
        $groups = \Torrents::get_groups(array_column($torrents, 'GroupID'));
        return array_map(function ($torrent) use ($groups) {
            return ['ID' => $torrent['ID'], 'Group' => $groups[$torrent['GroupID']]];
        }, $torrents);
    }

    public function twigGroups(array $results) {
        $releaseTypes = $this->releaseTypes;
        return array_reduce($results, function ($acc, $item) use ($releaseTypes) {
            $torrent = $item['ID'];
            $group = $item['Group'];
            $groupId = $group['ID'];
            $groupYear = $group['Year'];
            $groupName = $group['Name'];
            $groupFlags = isset($group['Flags']) ? $group['Flags'] : ['IsSnatched' => false];
            $groupTorrents = isset($group['Torrents']) ? $group['Torrents'] : [];
            $releaseType = $group['ReleaseType'];
            $tags = new \Tags($group['TagList']);
            $extendedArtists = $group['ExtendedArtists'];

            if (!empty($extendedArtists[1]) || !empty($extendedArtists[4]) || !empty($extendedArtists[5]) || !empty($extendedArtists[6])) {
                unset($extendedArtists[2]);
                unset($extendedArtists[3]);
                $displayName = \Artists::display_artists($extendedArtists);
            } else {
                $displayName = '';
            }
            $displayName .= "<a href=\"torrents.php?id=$groupId&amp;torrentid=$torrent#torrent$torrent\">$groupName";
            if ($groupYear > 0) {
                $displayName .= " [$groupYear]";
            }
            if ($releaseType > 0) {
                $displayName .= ' ['.$releaseTypes[$releaseType].']';
            }

            $extraInfo = \Torrents::torrent_info($groupTorrents[$torrent]);
            if ($extraInfo) {
                $displayName .= " - $extraInfo";
            }

            $displayName .= '</a>';

            $acc[$torrent] = [
                'group_id'   => $groupId,
                'snatched'   => $groupTorrents[$torrent]['IsSnatched'] ?? false,
                'name'       => $displayName,
                'tags'       => $tags->format(),
                'token'      => \Torrents::can_use_token($groupTorrents[$torrent]),
                'fl_message' => FL_confirmation_msg($groupTorrents[$torrent]['Seeders'], $groupTorrents[$torrent]['Size']),
            ];
            return $acc;
        }, []);
    }

    private $badMap = [
        'tags' => 'torrents_bad_tags',
        'folders' => 'torrents_bad_folders',
        'files' => 'torrents_bad_files',
        'lineage' => 'torrents_missing_lineage'
    ];

}
