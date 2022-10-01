<?php

$logId = (int)($_GET['logid'] ?? 0);
if (!$logId) {
    json_error('missing logid parameter');
}
$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    json_error('torrent not found');
}

(new Gazelle\Json\RipLog($torrent->id(), $logId))
    ->emit();
