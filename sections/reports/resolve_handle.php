<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    Error403::error();
}
authorize();

$report = new Manager\Report()->findById((int)($_POST['id'] ?? 0));
if (is_null($report)) {
    json_error('no report id');
}
if (
    !$Viewer->permitted('admin_reports')
    &&
    !in_array($report->subjectType(), ['comment', 'post', 'thread'])
) {
    Error403::error('forbidden ' . $report->subjectType());
}
$report->resolve($Viewer);

header('Location: reports.php');
