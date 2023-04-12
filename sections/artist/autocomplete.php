<?php

header('Content-Type: application/json; charset=utf-8');
if (empty($_GET['query'])) {
    echo json_encode([]);
    exit;
}

$fullName = trim(urldecode($_GET['query']));

$maxKeySize = 4;
if (strtolower(substr($fullName, 0, 4)) == 'the ') {
    $maxKeySize += 4;
}
$keySize = min($maxKeySize, max(1, strlen($fullName)));
$letters = mb_strtolower(mb_substr($fullName, 0, $keySize));

$key         = 'autocomplete_artist_' . $keySize . '_' . str_replace(' ', '%20', $letters);
$autoSuggest = $Cache->get($key);
if ($autoSuggest === false) {
    $db = Gazelle\DB::DB();
    $db->prepared_query("
        SELECT a.ArtistID,
            a.Name
        FROM artists_group AS a
        INNER JOIN torrents_artists AS ta ON (ta.ArtistID = a.ArtistID)
        INNER JOIN torrents AS t ON (t.GroupID = ta.GroupID)
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        WHERE a.Name LIKE ?
        GROUP BY ta.ArtistID
        ORDER BY tls.Snatched DESC
        LIMIT ?",
        str_replace('\\','\\\\',$letters) . '%', $keySize === $maxKeySize ? 250 : 20
    );
    $autoSuggest = $db->to_array(false, MYSQLI_NUM, false);
    $Cache->cache_value($key, $autoSuggest, 1800 + 7200 * ($maxKeySize - $keySize)); // Can't cache things for too long in case names are edited
}

$matched = 0;
$response = [
    'query' => $fullName,
    'suggestions' => []
];
foreach ($autoSuggest as $suggestion) {
    [$id, $name] = $suggestion;
    if (stripos($name, $fullName) === 0) {
        $response['suggestions'][] = ['value' => $name, 'data' => $id];
        if (++$matched > 19) {
            break;
        }
    }
}
echo json_encode($response);
