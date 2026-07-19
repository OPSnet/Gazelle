<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_moderate_forums') || empty($_POST['remove'])) {
    json_error('bad parameters');
}

$report = new Manager\Report()->findById((int)($_POST['id'] ?? 0));
if (is_null($report)) {
    json_error('no report id');
}
$report->claim(null);

print json_encode([
    'status' => 'success',
]);
