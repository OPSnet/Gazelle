<?php

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    json_error('forbidden');
}
authorize();

$report = (new Gazelle\Manager\Report)->findById((int)($_POST['reportid'] ?? 0));
if (is_null($report)) {
    json_error('no report id');
}
if (!$Viewer->permitted('admin_reports') && !in_array($report->subjectType(), ['comment', 'post', 'thread'])) {
    json_error('forbidden ' . $report->subjectType());
}
$report->resolve($Viewer, new Gazelle\Manager\Report);

echo json_encode(['status' => 'success']);
