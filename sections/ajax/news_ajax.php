<?php
//Don't allow bigger queries than specified below regardless of called function
$SizeLimit = 10;

$Offset = (int)$_GET['offset'];
$Count = (int)$_GET['count'];

if (!isset($_GET['count']) || !isset($_GET['offset']) || $Count <= 0 || $Offset < 0 || $Count > $SizeLimit) {
    json_die('failure');
}

$DB->prepared_query("
    SELECT
        ID,
        Title,
        Body,
        Time
    FROM news
    ORDER BY Time DESC
    LIMIT ?, ?
    ", $Offset, $Count
);

Text::$TOC = true;

$items = [];
while ($article = $DB->next_record(MYSQLI_NUM, false)) {
    [$id, $title, $body, $time] = $article;
    $items[] = [
        $id,
        Text::full_format($title),
        time_diff($time),
        Text::full_format($body),
    ];
}

json_print('success', ['items' => $items]);
