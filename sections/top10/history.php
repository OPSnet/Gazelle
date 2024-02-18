<?php

if (!$Viewer->permitted('users_mod')) {
    error(404);
}

$isByDay = trim($_GET['datetype'] ?? 'day')  == 'day';

$db = Gazelle\DB::DB();
if (empty($_GET['date'])) {
    $date = date('Y-m-d');
    $list = [];
} else {
    $date = trim($_GET['date']);
    if (!\Gazelle\Util\Time::validDate($date . ' 00:00:00')) {
        error('That does not look like a date');
    }
    $list = (new Gazelle\Manager\Torrent())->topTenHistoryList($date, $isByDay);
}

echo $Twig->render('top10/history.twig', [
    'by_day' => $isByDay,
    'date'   => $date,
    'list'   => $list,
    'viewer' => $Viewer,
]);
