<?php
if (isset($_GET['details'])) {
    if (in_array($_GET['details'],['ul','dl','numul','uls','dls'])) {
        $Details = $_GET['details'];
    } else {
        print json_encode(['status' => 'failure']);
        die();
    }
} else {
    $Details = 'all';
}

// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$Limit = in_array($Limit, [10,100,250]) ? $Limit : 10;
$db    = Gazelle\DB::DB();

$BaseQuery = "
    SELECT
        um.ID,
        um.Username,
        ui.JoinDate,
        uls.Uploaded,
        uls.Downloaded,
        GREATEST(uls.Uploaded - ?, 0) / (unix_timestamp(now()) - unix_timestamp(ui.JoinDate)) AS UpSpeed,
        uls.Downloaded / (unix_timestamp(now()) - unix_timestamp(ui.JoinDate)) AS DownSpeed,
        count(t.ID) AS NumUploads
    FROM users_main AS um
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
    INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
    LEFT JOIN torrents AS t ON (t.UserID = um.ID)
    WHERE um.Enabled = '1'
        AND uls.Uploaded > 5 * 1024 * 1024 * 1024
        AND uls.Downloaded > 5 * 1024 * 1024 * 1024
        AND (um.Paranoia IS NULL OR (um.Paranoia NOT LIKE '%\"uploaded\"%' AND um.Paranoia NOT LIKE '%\"downloaded\"%'))
    GROUP BY um.ID";
$args = [STARTING_UPLOAD, $Limit];

$OuterResults = [];
if ($Details == 'all' || $Details == 'ul') {
    if (!$TopUserUploads = $Cache->get_value("topuser_ul_$Limit")) {
        $db->prepared_query("
            $BaseQuery
            ORDER BY uls.Uploaded DESC
            LIMIT ?
            ", ...$args
        );
        $TopUserUploads = $db->to_array();
        $Cache->cache_value("topuser_ul_$Limit", $TopUserUploads, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Uploaders', 'ul', $TopUserUploads, $Limit);
}

if ($Details == 'all' || $Details == 'dl') {
    if (!$TopUserDownloads = $Cache->get_value("topuser_dl_$Limit")) {
        $db->prepared_query("
            $BaseQuery
            ORDER BY uls.Downloaded DESC
            LIMIT ?
            ", ...$args
        );
        $TopUserDownloads = $db->to_array();
        $Cache->cache_value("topuser_dl_$Limit", $TopUserDownloads, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Downloaders', 'dl', $TopUserDownloads, $Limit);
}

if ($Details == 'all' || $Details == 'numul') {
    if (!$TopUserNumUploads = $Cache->get_value("topuser_numul_$Limit")) {
        $db->prepared_query("
            $BaseQuery
            ORDER BY NumUploads DESC
            LIMIT ?
            ", ...$args
        );
        $TopUserNumUploads = $db->to_array();
        $Cache->cache_value("topuser_numul_$Limit", $TopUserNumUploads, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Torrents Uploaded', 'numul', $TopUserNumUploads, $Limit);
}

if ($Details == 'all' || $Details == 'uls') {
    if (!$TopUserUploadSpeed = $Cache->get_value("topuser_ulspeed_$Limit")) {
        $db->prepared_query("
            $BaseQuery
            ORDER BY UpSpeed DESC
            LIMIT ?
            ", ...$args
        );
        $TopUserUploadSpeed = $db->to_array();
        $Cache->cache_value("topuser_ulspeed_$Limit", $TopUserUploadSpeed, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Fastest Uploaders', 'uls', $TopUserUploadSpeed, $Limit);
}

if ($Details == 'all' || $Details == 'dls') {
    if (!$TopUserDownloadSpeed = $Cache->get_value("topuser_dlspeed_$Limit")) {
        $db->prepared_query("
            $BaseQuery
            ORDER BY DownSpeed DESC
            LIMIT ?
            ", ...$args
        );
        $TopUserDownloadSpeed = $db->to_array();
        $Cache->cache_value("topuser_dlspeed_$Limit", $TopUserDownloadSpeed, 3600 * 12);
    }
    $OuterResults[] = generate_user_json('Fastest Downloaders', 'dls', $TopUserDownloadSpeed, $Limit);
}

print json_encode([
    'status' => 'success',
    'response' => $OuterResults
]);

function generate_user_json(string $Caption, string $Tag, array $Details, int $Limit): array {
    $results = [];
    foreach ($Details as $Detail) {
        $results[] = [
            'id' => (int)$Detail['ID'],
            'username' => $Detail['Username'],
            'uploaded' => (float)$Detail['Uploaded'],
            'upSpeed' => (float)$Detail['UpSpeed'],
            'downloaded' => (float)$Detail['Downloaded'],
            'downSpeed' => (float)$Detail['DownSpeed'],
            'numUploads' => (int)$Detail['NumUploads'],
            'joinDate' => $Detail['JoinDate']
        ];
    }
    return [
        'caption' => $Caption,
        'tag' => $Tag,
        'limit' => $Limit,
        'results' => $results
    ];
}
