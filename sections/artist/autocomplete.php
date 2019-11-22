<?php
if (empty($_GET['query'])) {
    error(0);
}
header('Content-Type: application/json; charset=utf-8');

$fullName = rawurldecode($_GET['query']);

$maxKeySize = 4;
if (strtolower(substr($fullName, 0, 4)) == 'the ') {
    $maxKeySize += 4;
}
$keySize = min($maxKeySize, max(1, strlen($fullName)));

$letters = mb_strtolower(mb_substr($fullName, 0, $keySize));
$autoSuggest = $Cache->get('autocomplete_artist_' . $keySize . '_' . $letters);

if (!$autoSuggest) {
    $limit = (($keySize === $maxKeySize) ? 250 : 10);
    $DB->prepared_query("
        SELECT
            a.ArtistID,
            a.Name
        FROM artists_group AS a
        INNER JOIN torrents_artists AS ta ON (ta.ArtistID = a.ArtistID)
        INNER JOIN torrents AS t ON (t.GroupID = ta.GroupID)
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        WHERE a.Name LIKE ?
        GROUP BY ta.ArtistID
        ORDER BY tls.Snatched DESC
        LIMIT ?",
        str_replace('\\','\\\\',$letters) . '%', $limit);
    $autoSuggest = $DB->to_array(false, MYSQLI_NUM, false);
    $Cache->cache_value('autocomplete_artist_' . $keySize . '_' . $letters, $autoSuggest, 1800 + 7200 * ($maxKeySize - $keySize)); // Can't cache things for too long in case names are edited
}

$matched = 0;
$response = [
    'query' => $fullName,
    'suggestions' => []
];
foreach ($autoSuggest as $suggestion) {
    list($id, $name) = $suggestion;
    if (stripos($name, $fullName) === 0) {
        $response['suggestions'][] = ['value' => $name, 'data' => $id];
        if (++$matched > 9) {
            break;
        }
    }
}
echo json_encode($response);
