<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use Gazelle\Util\Time;

$torMan        = new Gazelle\Manager\Torrent();
$reportMan     = new Gazelle\Manager\Torrent\Report($torMan);
$reportTypeMan = new Gazelle\Manager\Torrent\ReportType();

$torrent = $torMan->findById((int)($_GET['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('This torrent has already been deleted.');
}

if ($torrent->hasUploadLock()) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

if ($Viewer->id() != $torrent->uploaderId() && !$Viewer->permitted('torrents_delete')) {
    error(403);
}

if ($Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !$Viewer->permitted('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}

if (Time::timeAgo($torrent->created()) > 3600 * 24 * 7 && !$Viewer->permitted('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been uploaded for over a week. If you now think there is a problem, please report the torrent instead.');
}

if ($torrent->snatchTotal() >= 5 && !$Viewer->permitted('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been snatched by 5 or more users. If you believe there is a problem with this torrent, please report it instead.');
}

echo $Twig->render('torrent/remove.twig', [
    'report_category_list' => $reportTypeMan->categoryList($torrent->group()->categoryId()),
    'report_type'          => $reportTypeMan->findByType('dupe'),
    'request_list'         => $torrent->requestFills(new Gazelle\Manager\Request()),
    'torrent'              => $torrent,
    'viewer'               => $Viewer,
]);
