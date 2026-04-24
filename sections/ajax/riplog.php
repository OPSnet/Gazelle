<?php

declare(strict_types=1);

namespace Gazelle;

$logId = (int)($_GET['logid'] ?? 0);
if (!$logId) {
    json_error('missing logid parameter');
}
$torrent = new Manager\Torrent()->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    json_error('torrent not found');
}

echo new Json\Riplog($torrent, $logId)
    ->setVersion(2)
    ->response();
