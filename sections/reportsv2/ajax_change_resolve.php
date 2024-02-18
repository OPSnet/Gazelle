<?php
/*
 * This is the page that gets the values of whether to delete/disable upload/warning duration
 * every time you change the resolve type on one of the two reports pages.
 */

if (!$Viewer->permitted('admin_reports')) {
    json_error("forbidden");
}

$reportType = (new Gazelle\Manager\Torrent\ReportType())->findByType($_GET['type'] ?? '');
if (is_null($reportType)) {
    json_error("bad type");
}

echo json_encode($reportType->resolveOptions());
