<?php

enforce_login();

switch ($_REQUEST['action']) {
    case 'collage':
        $DB->query("
SELECT r1.ID
FROM collages AS r1 JOIN
   (SELECT (RAND() *
                 (SELECT MAX(ID)
                    FROM collages)) AS ID)
    AS r2
WHERE r1.ID >= r2.ID
ORDER BY r1.ID ASC
LIMIT 1");
        $collage = $DB->next_record();
        header("Location: collages.php?id={$collage['ID']}");
        break;
    case 'artist':
        $DB->query("
SELECT r1.ArtistID
FROM artists_group AS r1 JOIN
   (SELECT (RAND() *
                 (SELECT MAX(ArtistID)
                    FROM artists_group)) AS ArtistID)
    AS r2
WHERE r1.ArtistID >= r2.ArtistID
ORDER BY r1.ArtistID ASC
LIMIT 1");
        $artist = $DB->next_record();
        header("Location: artist.php?id={$artist['ArtistID']}");
        break;
    case 'torrent':
    default:
        $DB->query("
SELECT r1.ID
FROM torrents_group AS r1 JOIN
   (SELECT (RAND() *
                 (SELECT MAX(ID)
                    FROM torrents_group)) AS ID)
    AS r2
WHERE r1.ID >= r2.ID
ORDER BY r1.ID ASC
LIMIT 1");
        $torrent = $DB->next_record();
        header("Location: torrents.php?id={$torrent['ID']}");
        break;
}
