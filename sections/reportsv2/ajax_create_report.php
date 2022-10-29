<?php
/*
 * This page is for creating a report using AJAX.
 * It should have the following posted fields:
 *     [auth] => AUTH_KEY
 *    [torrentid] => TORRENT_ID
 *    [type] => TYPE
 *    [otherid] => OTHER_ID
 *
 * It should not be used on site as is, except in its current use (Switch) as it is lacking for any purpose but this.
 */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

if (!isset($_POST['type'])) {
    json_die('Missing Type');
}

authorize();

$torMan = new Gazelle\Manager\Torrent;
$torrent = $torMan->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    json_die('No Torrent ID');
}

$reportMan = new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent);
if ($reportMan->existsRecent($torrent->id(), $Viewer->id())) {
    exit;
}

$reason = trim($_POST['extra'] ?? '');
$report = $reportMan->create($torrent, $Viewer, $_POST['type'] ?? 'other', $reason, (int)$_POST['otherid']);

if ($torrent->uploaderId() != $Viewer->id()) {
    (new Gazelle\Manager\User)->sendPM($torrent->uploaderId(), 0,
        "One of your torrents has been reported",
        $Twig->render('reportsv2/new.twig', [
            'id'     => $torrent->id(),
            'title'  => $report->reportType()['title'],
            'reason' => $reason,
        ])
    );
}

echo $report->id();
