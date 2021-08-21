<?php

$json = new \Gazelle\Json\AddLog;
$torrentid = (int)($_GET['id'] ?? 0);

if (empty($torrentid)) {
    $json->failure('bad parameters');
    exit;
} else if ($json->findTorrentById($torrentid) === null) {
    exit;
} else if (empty($_FILES) || empty($_FILES['logfiles'])) {
    $json->failure('no log files uploaded');
    exit;
}

$json->setVersion(1)
    ->setViewerId($Viewer->id())
    ->setLogFiles($_FILES['logfiles'])
    ->emit();
