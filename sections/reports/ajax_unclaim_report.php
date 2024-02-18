<?php

if (!$Viewer->permitted('site_moderate_forums') || empty($_POST['remove'])) {
    json_error('bad parameters');
}

$report = (new Gazelle\Manager\Report(new Gazelle\Manager\User()))->findById((int)($_POST['id'] ?? 0));
if (is_null($report)) {
    json_error('no report id');
}
$report->claim(null);

print json_encode([
    'status' => 'success',
]);
