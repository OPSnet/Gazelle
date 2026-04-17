<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error404::error();
}

$isByDay = trim($_GET['datetype'] ?? 'day')  == 'day';

$db = DB::DB();
if (empty($_GET['date'])) {
    $date = date('Y-m-d');
    $list = [];
} else {
    $date = trim($_GET['date']);
    if (!Util\Time::isValidDateTime($date, 'Y-m-d')) {
        Error400::error('That does not look like a date');
    }
    $list = new Manager\Torrent()->topTenHistoryList($date, $isByDay);
}

echo $Twig->render('top10/history.twig', [
    'by_day' => $isByDay,
    'date'   => $date,
    'list'   => $list,
    'viewer' => $Viewer,
]);
