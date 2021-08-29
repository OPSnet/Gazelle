<?php

switch ($_REQUEST['action'] ?? '') {
    case 'artist':
        $page = 'artist';
        $id = $DB->scalar("
            SELECT r1.ArtistID
            FROM artists_group AS r1
            INNER JOIN torrents_artists ta ON (ta.ArtistID = r1.ArtistID AND ta.Importance IN ('1', '3', '4', '5', '6', '7')) /* exclude as guest */
            INNER JOIN (SELECT (RAND() * (SELECT MAX(ArtistID) FROM artists_group)) AS ArtistID) AS r2
            WHERE r1.ArtistID BETWEEN r2.ArtistID and r2.ArtistID + 100
            GROUP BY r1.ArtistID
            HAVING count(*) >= ?
            ORDER BY r1.ArtistID ASC
            LIMIT 1
            ", RANDOM_ARTIST_MIN_ENTRIES
        );
        break;

    case 'collage':
        $page = 'collages';
        $id = $DB->scalar("
            SELECT r1.ID
            FROM collages AS r1
            INNER JOIN collages_torrents ct ON (ct.CollageID = r1.ID)
            INNER JOIN (SELECT (RAND() * (SELECT MAX(ID) FROM collages)) AS ID) AS r2
            WHERE r1.ID BETWEEN r2.ID and r2.ID + 100
                AND r1.Deleted = '0'
            GROUP BY r1.ID
            HAVING count(*) >= ?
            ORDER BY r1.ID ASC
            LIMIT 1
            ", RANDOM_COLLAGE_MIN_ENTRIES
        );
        break;

    case 'torrent':
    default:
        $page = 'torrents';
        $id = $DB->scalar("
            SELECT r1.ID
            FROM torrents_group AS r1
            INNER JOIN torrents t ON (r1.ID = t.GroupID)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID AND tls.Seeders >= ?)
            INNER JOIN (SELECT (RAND() * (SELECT MAX(ID) FROM torrents_group)) AS ID) AS r2
            WHERE r1.ID BETWEEN r2.ID and r2.ID + 200
            ORDER BY r1.ID ASC
            LIMIT 1
            ", RANDOM_TORRENT_MIN_SEEDS
        );
        break;
}

if (is_null($id)) {
    error(404); /* only likely to happen on a brand new installation */
}
header("Location: $page.php?id=$id");
