<?php

// error out on invalid requests (before caching)
if (isset($_GET['details'])) {
    if (in_array($_GET['details'],['ut','ur','v'])) {
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
$Limit = in_array($Limit, [10, 100, 250]) ? $Limit : 10;
$OuterResults = [];

$db = Gazelle\DB::DB();

if ($Details == 'all' || $Details == 'ut') {
    if (!$TopUsedTags = $Cache->get_value("topusedtag_$Limit")) {
        $db->prepared_query("
            SELECT
                t.ID,
                t.Name,
                count(*) AS Uses,
                SUM(tt.PositiveVotes - 1) AS PosVotes,
                SUM(tt.NegativeVotes - 1) AS NegVotes
            FROM tags AS t
            INNER JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
            GROUP BY tt.TagID
            ORDER BY Uses DESC
            LIMIT ?
            ", $Limit
        );
        $TopUsedTags = $db->to_array();
        $Cache->cache_value("topusedtag_$Limit", $TopUsedTags, 3600 * 12);
    }
    $OuterResults[] = generate_tag_json('Most Used Torrent Tags', 'ut', $TopUsedTags, $Limit);
}

if ($Details == 'all' || $Details == 'ur') {
    if (!$TopRequestTags = $Cache->get_value("toprequesttag_$Limit")) {
        $db->prepared_query("
            SELECT
                t.ID,
                t.Name,
                count(*) AS Uses,
                '',''
            FROM tags AS t
            INNER JOIN requests_tags AS r ON (r.TagID = t.ID)
            GROUP BY r.TagID
            ORDER BY Uses DESC
            LIMIT ?
            ", $Limit
        );
        $TopRequestTags = $db->to_array();
        $Cache->cache_value("toprequesttag_$Limit", $TopRequestTags, 3600 * 12);
    }
    $OuterResults[] = generate_tag_json('Most Used Request Tags', 'ur', $TopRequestTags, $Limit);
}

if ($Details == 'all' || $Details == 'v') {
    if (!$TopVotedTags = $Cache->get_value("topvotedtag_$Limit")) {
        $db->prepared_query("
            SELECT
                t.ID,
                t.Name,
                count(*) AS Uses,
                SUM(tt.PositiveVotes - 1) AS PosVotes,
                SUM(tt.NegativeVotes - 1) AS NegVotes
            FROM tags AS t
            INNER JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
            GROUP BY tt.TagID
            ORDER BY PosVotes DESC
            LIMIT ?
            ", $Limit
        );
        $TopVotedTags = $db->to_array();
        $Cache->cache_value("topvotedtag_$Limit", $TopVotedTags, 3600 * 12);
    }
    $OuterResults[] = generate_tag_json('Most Highly Voted Tags', 'v', $TopVotedTags, $Limit);
}

print json_encode( [
    'status' => 'success',
    'response' => $OuterResults
]);

function generate_tag_json(string $Caption, string $Tag, array $Details, int $Limit): array {
    $results = [];
    foreach ($Details as $Detail) {
        $results[] = [
            'name' => $Detail['Name'],
            'uses' => (int)$Detail['Uses'],
            'posVotes' => (int)$Detail['PosVotes'],
            'negVotes' => (int)$Detail['NegVotes']
        ];
    }

    return [
        'caption' => $Caption,
        'tag' => $Tag,
        'limit' => $Limit,
        'results' => $results
    ];
}
