<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Util\Time;

$torMan        = new Manager\Torrent();
$reportMan     = new Manager\Torrent\Report($torMan);
$reportTypeMan = new Manager\Torrent\ReportType();

$torrent = $torMan->findById((int)($_GET['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error400::error('This torrent has already been deleted.');
}

if ($torrent->hasUploadLock()) {
    Error400::error(
        'Torrent cannot be deleted because the upload process is not completed yet. Please try again later.'
    );
}

if ($Viewer->id != $torrent->uploaderId() && !$Viewer->permitted('torrents_delete')) {
    Error403::error();
}

if (
    $Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX
    && !$Viewer->permitted('torrents_delete_fast')
) {
    Error400::error(
        'You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. If you think there is a problem with this torrent, please report it with [RP] instead.'
    );
}

if (
    Time::timeAgo($torrent->created()) > 86400 * 7
    && !$Viewer->permitted('torrents_delete')
) {
    // Should this be torrents_delete or torrents_delete_fast?
    Error400::error(
        'You can no longer delete this torrent as it has been uploaded for over a week. If you now think there is a problem, please report the torrent with [RP] instead.'
    );
}

if ($torrent->snatchTotal() >= 5 && !$Viewer->permitted('torrents_delete')) {
    // Should this be torrents_delete or torrents_delete_fast?
    Error400::error(
        'You can no longer delete this torrent as it has been snatched by 5 or more users. If you now think there is a problem, please report the torrent with [RP] instead.'
    );
}

echo $Twig->render('torrent/remove.twig', [
    'report_category_list' => $reportTypeMan->categoryList($torrent->group()->categoryId()),
    'report_type'          => $reportTypeMan->findByType('dupe'),
    'request_list'         => $torrent->requestFills(),
    'torrent'              => $torrent,
    'viewer'               => $Viewer,
]);
