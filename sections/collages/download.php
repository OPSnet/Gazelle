<?php
/*
This page is something of a hack so those
easily scared off by funky solutions, don't
touch it! :P

There is a central problem to this page, it's
impossible to order before grouping in SQL, and
it's slow to run sub queries, so we had to get
creative for this one.

The solution I settled on abuses the way
$DB->to_array() works. What we've done, is
backwards ordering. The results returned by the
query have the best one for each GroupID last,
and while to_array traverses the results, it
overwrites the keys and leaves us with only the
desired result. This does mean however, that
the SQL has to be done in a somewhat backwards
fashion.

Thats all you get for a disclaimer, just
remember, this page isn't for the faint of
heart. -A9

SQL template:
SELECT
    CASE
        WHEN t.Format = 'MP3' AND t.Encoding = 'V0 (VBR)'
            THEN 1
        WHEN t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)'
            THEN 2
        ELSE 100
    END AS Rank,
    t.GroupID,
    t.Media,
    t.Format,
    t.Encoding,
    IF(t.Year = 0, tg.Year, t.Year),
    tg.Name,
    a.Name,
    t.Size
FROM torrents AS t
INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
INNER JOIN collages_torrents AS c ON t.GroupID = c.GroupID AND c.CollageID = '8'
INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID AND tg.CategoryID = '1'
LEFT JOIN artists_group AS a ON a.ArtistID = tg.ArtistID
ORDER BY t.GroupID ASC, Rank DESC, tls.Seeders ASC
*/

if (!check_perms('zip_downloader')) {
    error(403);
}

if (
    !isset($_REQUEST['collageid'])
    || !isset($_REQUEST['preference'])
    || !is_number($_REQUEST['preference'])
    || !is_number($_REQUEST['collageid'])
    || $_REQUEST['preference'] > 2
    || count($_REQUEST['list']) === 0
) {
    error(0);
}

$orderBy = ['t.RemasterTitle DESC', 'tls.Seeders ASC', 't.Size ASC'];

$collage = new Gazelle\Collage((int)$_REQUEST['collageid']);
$Collector = $collage->zipCollector($orderBy[$_REQUEST['preference']], $_REQUEST['list']);

$Settings = [implode(':', $_REQUEST['list']), $_REQUEST['preference']];
if (!isset($LoggedUser['Collector']) || $LoggedUser['Collector'] != $Settings) {
    Users::update_site_options($LoggedUser['ID'], ['Collector' => $Settings]);
}

$Collector->finalize();
define('SKIP_NO_CACHE_HEADERS', 1);
