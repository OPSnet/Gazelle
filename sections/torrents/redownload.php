<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\UserTorrentSearch;

if ($Viewer->isStaff() && isset($_GET['userid'])) {
    $user = new Manager\User()->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
} else {
    $user = $Viewer;
}

$userTorrent = new Search\UserTorrent(
    $user,
    match ($_GET['type']) {
        'seeding'  => UserTorrentSearch::seeding,
        'snatches' => UserTorrentSearch::snatched,
        default    => UserTorrentSearch::uploaded,
    }
);

$title     = "{$user->username()}-{$userTorrent->label()}";
$collector = new Collector\TList($Viewer, new Manager\Torrent(), $title, 0);
$idList    = $userTorrent->idList();
$collector->setList($idList);
$collector->prepare([]);
set_time_limit((int)max(30, count($idList) * 0.1));
$collector->emitZip(Util\Zip::make($title));
