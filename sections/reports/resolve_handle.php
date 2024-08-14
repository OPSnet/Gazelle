<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    error(403);
}
authorize();

$report = (new Gazelle\Manager\Report(new Gazelle\Manager\User()))->findById((int)($_POST['id'] ?? 0));
if (is_null($report)) {
    json_error('no report id');
}
if (!$Viewer->permitted('admin_reports') && !in_array($report->subjectType(), ['comment', 'post', 'thread'])) {
    error('forbidden ' . $report->subjectType());
}
$report->resolve($Viewer);

header('Location: reports.php');
