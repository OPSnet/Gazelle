<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_moderate_forums')) {
    json_error('forbidden');
}
$report = (new Gazelle\Manager\Report(new Gazelle\Manager\User()))->findById((int)($_POST['id'] ?? 0));
if (is_null($report)) {
    json_error('bad post id');
}
$report->addNote($_POST['notes']);

print json_encode(['status' => 'success']);
