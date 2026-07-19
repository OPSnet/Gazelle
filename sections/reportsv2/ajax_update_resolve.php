<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

// perform the back end of updating a resolve type

if (!$Viewer->permitted('admin_reports')) {
    json_die("failure", "forbidden");
}

authorize();

$reportType = new Manager\Torrent\ReportType()->findByType($_GET['newresolve'] ?? '');
if (is_null($reportType)) {
    json_error("bad newresolve");
}

$report = new Manager\Torrent\Report(new Manager\Torrent())->findById((int)($_GET['reportid'] ?? 0));
if (is_null($report)) {
    json_error("bad reportid");
}

json_print("success", [
    'old'     => $report->reportType()->type(),
    'new'     => $reportType->type(),
    'success' => $report->changeType($reportType),
]);
