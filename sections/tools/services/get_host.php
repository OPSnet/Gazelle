<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

header('Content-Type: application/json; charset=text/plain');
if (!$Viewer->permitted('users_view_ips')) {
    echo '"Forbidden"';
    return;
}
echo json_hostname(trim($_GET['ip'] ?? ''));
