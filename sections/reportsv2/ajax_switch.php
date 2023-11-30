<?php
/*
 * This page is for creating a report using AJAX.
 * It should have the following posted fields:
 *    [auth]
 *    [reportid]  this report
 *    [otherid]   related torrent
 *
 * It should not be used on site as is, except in its current use (Switch) as it is lacking for any purpose but this.
 */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

authorize();
$torMan = new Gazelle\Manager\Torrent;
$other = $torMan->findById((int)$_POST['otherid']);
if (is_null($other)) {
    json_error("bad other id");
}

$reportMan = new Gazelle\Manager\Torrent\Report($torMan);
if ($reportMan->existsRecent($other->id(), $Viewer->id())) {
    json_error("too soon");
}
$report = $reportMan->findById((int)($_POST['reportid'] ?? 0));
if (is_null($report)) {
    json_error("bad report id");
}

$new = $reportMan->create(
    torrent:     $other,
    user:        new Gazelle\User($report->reporterId()),
    reportType:  $report->reportType(),
    reason:      $report->reason(),
    image:       implode(' ', $report->image()),
    otherIdList: (string)$report->torrentId(),
);

if ($other->uploaderId() != $Viewer->id()) {
    $other->uploader()->inbox()->createSystem(
        "One of your torrents has been reported",
        $Twig->render('reportsv2/new.twig', [
            'id'     => $other->id(),
            'title'  => $new->reportType()->name(),
            'reason' => $new->reason(),
        ])
    );
}

echo $new->id();
