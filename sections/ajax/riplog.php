<?php

$logId = (int)$_GET['logid'];
if (!$logId) {
    json_die('missing logid parameter');
}
$torrent = (new Gazelle\Manager\Torrent)->findTorrentById((int)$_GET['id']);
if (is_null($torrent)) {
    json_die('missing id parameter');
}
(new Gazelle\Json\RipLog($torrent->id(), $logId))->emit();
