<?php

if (!$ByMonth = $Cache->get_value('stats_torrents_upload')) {
    $Labels = [];
    $InFlow = [];
    $OutFlow = [];
    $NetFlow = [];

    $DB->prepared_query("
        SELECT DATE_FORMAT(Time,'%b \'%y') AS Month, COUNT(ID)
        FROM log
        WHERE Message LIKE 'Torrent % was uploaded by %'
        GROUP BY Month
        ORDER BY Time DESC
        LIMIT 1, 12");
    $TimelineIn = array_reverse($DB->to_array(false, MYSQLI_BOTH, false));
    $DB->prepared_query("
        SELECT DATE_FORMAT(Time,'%b \'%y') AS Month, COUNT(ID)
        FROM log
        WHERE Message LIKE 'Torrent % was deleted %'
        GROUP BY Month
        ORDER BY Time DESC
        LIMIT 1, 12");
    $TimelineOut = array_reverse($DB->to_array(false, MYSQLI_BOTH, false));
    $DB->prepared_query("
        SELECT DATE_FORMAT(Time,'%b \'%y') AS Month, COUNT(ID)
        FROM torrents
        GROUP BY Month
        ORDER BY Time DESC
        LIMIT 1, 12");
    $TimelineNet = array_reverse($DB->to_array(false, MYSQLI_BOTH, false));

    foreach ($TimelineIn as $Month) {
        list($Label, $Amount) = $Month;
        $Labels[] = $Label;
        $InFlow[$Label] = $Amount;
    }
    foreach ($TimelineOut as $Month) {
        list($Label, $Amount) = $Month;
        $OutFlow[$Label] = $Amount;
    }
    foreach ($TimelineNet as $Month) {
        list($Label, $Amount) = $Month;
        $NetFlow[$Label] = $Amount;
    }
    $ByMonth = [];
    for ($i = 0; $i < count($Labels); $i++) {
        $Label = $Labels[$i];
        $ByMonth[$Label] = [
            'uploads' => isset($InFlow[$Label]) ? $InFlow[$Label] : 0,
            'deletions' => isset($OutFlow[$Label]) ? $OutFlow[$Label] : 0,
            'remaining' => isset($NetFlow[$Label]) ? $NetFlow[$Label] : 0
        ];
    }
    $Cache->cache_value('stats_torrents_upload', $ByMonth, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for dec -> jan
}

if (!$ByCategory = $Cache->get_value('stats_torrents_category')) {
    $DB->prepared_query("
        SELECT tg.CategoryID, COUNT(t.ID) AS Torrents
        FROM torrents AS t
            JOIN torrents_group AS tg ON tg.ID = t.GroupID
        GROUP BY tg.CategoryID
        ORDER BY Torrents DESC");
    $Groups = $DB->to_array(false, MYSQLI_BOTH, false);
    $ByCategory = [];
    foreach ($Groups as $Group) {
        list($CategoryID, $Torrents) = $Group;
        $ByCategory[$Categories[$CategoryID - 1]] = $Torrents;
    }
    $Cache->cache_value('stats_torrents_category', $ByCategory, 3600 * 24);
}


print(json_encode(
    [
        'status' => 'success',
        'response' => [
            'uploads_by_month' => $ByMonth,
            'torrents_by_category' => $ByCategory,
        ]
    ]
));
