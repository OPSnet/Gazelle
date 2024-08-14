<?php
/** @phpstan-var \Gazelle\User $Viewer */

// perform the back end of updating a resolve type

if (!$Viewer->permitted('admin_reports')) {
    json_die("failure", "forbidden");
}

authorize();

$reportType = (new Gazelle\Manager\Torrent\ReportType())->findByType($_GET['newresolve'] ?? '');
if (is_null($reportType)) {
    json_error("bad newresolve");
}

$report = (new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent()))->findById((int)($_GET['reportid'] ?? 0));
if (is_null($report)) {
    json_error("bad reportid");
}

json_print("success", [
    'old'     => $report->reportType()->type(),
    'new'     => $reportType->type(),
    'success' => $report->changeType($reportType),
]);
