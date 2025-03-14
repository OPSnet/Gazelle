<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    json_error('bad parameters');
}

$report = (new Gazelle\Manager\Report(new Gazelle\Manager\User()))->findById((int)($_POST['id'] ?? 0));
if (is_null($report)) {
    json_error('no report id');
}

if ($report->isClaimed()) {
    print json_encode([
        'status' => 'dupe'
    ]);
} else {
    $report->claim($Viewer);
    print json_encode([
        'status' => 'success',
        'username' => $Viewer->username()
    ]);
}
