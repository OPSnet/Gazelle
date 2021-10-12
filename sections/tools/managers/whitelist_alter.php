<?php

if (!$Viewer->permitted('admin_whitelist')) {
    error(403);
}

authorize();

$whitelist = new Gazelle\Manager\ClientWhitelist;

if ($_POST['submit'] == 'Delete') {
    $clientId = (int)$_POST['id'];
    if ($clientId < 1) {
        error(0);
    }
    $peer = $whitelist->peerId($clientId);
    $whitelist->remove($clientId);
    (new Gazelle\Tracker)->update_tracker('remove_whitelist', ['peer_id' => $peer]);
} else {
    // Edit or Create
    if (empty($_POST['client']) || empty($_POST['peer_id'])) {
        error(0);
    }
    $peer    = trim($_POST['peer_id']);
    $vstring = trim($_POST['client']);

    $tracker = new Gazelle\Tracker;
    if ($_POST['submit'] == 'Edit') {
        $clientId = (int)$_POST['id'];
        if ($clientId < 1) {
            error(0);
        }
        $oldPeer = $whitelist->modify($clientId, $peer, $vstring);
        $tracker->update_tracker('edit_whitelist', [
            'old_peer_id' => $oldPeer,
            'new_peer_id' => $peer,
        ]);
    } else {
        $tracker->update_tracker('add_whitelist', [
            'peer_id' => $whitelist->create($peer, $vstring)
        ]);
    }
}

header('Location: tools.php?action=whitelist');
