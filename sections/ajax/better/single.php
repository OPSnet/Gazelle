<?php
if (($Results = $Cache->get_value('better_single_groupids')) === false) {
    $DB->prepared_query("
        SELECT t.ID AS TorrentID,
            t.GroupID AS GroupID
        FROM xbt_files_users AS x
        INNER JOIN torrents AS t ON (t.ID = x.fid)
        WHERE t.Format = 'FLAC'
        GROUP BY x.fid
        HAVING COUNT(x.uid) = 1
        ORDER BY t.LogScore DESC, t.Time ASC
        LIMIT 30
    ");

    $Results = $DB->to_pair('GroupID', 'TorrentID', false);
    $Cache->cache_value('better_single_groupids', $Results, 3600);
}

$Groups = Torrents::get_groups(array_keys($Results));

$JsonResults = [];
foreach ($Results as $GroupID => $TorrentID) {
    if (!isset($Groups[$GroupID])) {
        continue;
    }
    $Group = $Groups[$GroupID];

    $JsonArtists = [];
    if (count($Group['Artists'])) {
        foreach ($Group['Artists'] as $Artist) {
            $JsonArtists[] = [
                'id' => (int)$Artist['id'],
                'name' => $Artist['name'],
                'aliasId' => (int)$Artist['aliasid']
            ];
        }
    }

    $JsonResults[] = [
        'torrentId' => (int)$TorrentID,
        'groupId' => (int)$GroupID,
        'artist' => $JsonArtists,
        'groupName' => $Group['GroupName'],
        'groupYear' => (int)$Group['GroupYear'],
        'downloadUrl' => "torrents.php?action=download&id=$TorrentID&torrent_pass=" . $LoggedUser['torrent_pass'],
    ];
}

print json_encode(
    [
        'status' => 'success',
        'response' => $JsonResults
    ]
);
