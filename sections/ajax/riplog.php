<?php

$logId = (int)$_GET['logid'];
if (!$logId) {
    json_error('missing logid parameter');
}
$torrent = (new Gazelle\Manager\Torrent)->findTorrentById((int)$_GET['id']);
if (is_null($torrent)) {
    json_error('torrent not found');
}
try {
    $ripLog = new Gazelle\Json\RipLog($torrent->id(), $logId);
} catch (Gazelle\Exception\ResourceNotFoundException $e) {
    json_error('inconsistent id/logid parameters');
}
$ripLog->emit();
