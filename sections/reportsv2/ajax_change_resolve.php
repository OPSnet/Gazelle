<?php
/*
 * This is the page that gets the values of whether to delete/disable upload/warning duration
 * every time you change the resolve type on one of the two reports pages.
 */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

$reportMan = new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent);
$report = $reportMan->findById((int)($_GET['id'] ?? 0));
if (is_null($report)) {
    json_error(404);
}

echo json_encode($reportMan->resolveOptions($_GET['type'] ?? ''));
