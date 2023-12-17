<?php

if (!$Viewer->permitted('admin_whitelist')) {
    error(403);
}

authorize();

$tracker   = new Gazelle\Tracker;
$whitelist = new Gazelle\Manager\ClientWhitelist;

$submitAction = $_POST['submit'] ?? null;

if ($submitAction === 'Delete') {
    $clientId = (int)$_POST['id'];
    if (!$clientId) {
        error(0);
    }
    $tracker->removeWhitelist($whitelist->peerId($clientId));
    $whitelist->remove($clientId);
} else {
    // Edit or Create
    if (empty($_POST['client']) || empty($_POST['peer_id'])) {
        error(0);
    }
    $peer    = trim($_POST['peer_id']);
    $vstring = trim($_POST['client']);

    if ($submitAction === 'Create') {
        $whitelist->create($peer, $vstring);
        $tracker->addWhitelist($peer);
    } else {
        $clientId = (int)($_POST['id'] ?? 0);
        if (!$clientId) {
            error(0);
        }
        $tracker->modifyWhitelist(old: $whitelist->modify($clientId, $peer, $vstring), new: $peer);
    }
}

header('Location: tools.php?action=whitelist');
