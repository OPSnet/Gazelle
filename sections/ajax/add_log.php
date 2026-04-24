<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$torrent = new Manager\Torrent()->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    json_error('bad parameters');
}
if ($torrent->uploaderId() != $Viewer->id && !$Viewer->permitted('admin_add_log')) {
    json_error('not your upload');
}
if (!isset($_FILES['logfiles'])) {
    json_error('no log files uploaded');
}

echo new Json\AddLog(
    $torrent,
    $_FILES['logfiles'],
)
    ->setVersion(1)
    ->response();
