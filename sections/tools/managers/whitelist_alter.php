<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_whitelist')) {
    Error403::error();
}

authorize();

$tracker   = new Tracker();
$whitelist = new Manager\ClientWhitelist();

$submitAction = $_POST['submit'] ?? null;

if ($submitAction === 'Delete') {
    $clientId = (int)$_POST['id'];
    if (!$clientId) {
        Error404::error('Whitelist client id not found for delete');
    }
    $tracker->removeWhitelist($whitelist->peerId($clientId));
    $whitelist->remove($clientId);
} else {
    // Edit or Create
    if (empty($_POST['client']) || empty($_POST['peer_id'])) {
        Error404::error('Whitelist client id not found for create/edit');
    }
    $peer    = trim($_POST['peer_id']);
    $vstring = trim($_POST['client']);

    if ($submitAction === 'Create') {
        $whitelist->create($peer, $vstring);
        $tracker->addWhitelist($peer);
    } else {
        $clientId = (int)($_POST['id'] ?? 0);
        if (!$clientId) {
            Error404::error('Whitelist client id not found for edit');
        }
        $tracker->modifyWhitelist(old: $whitelist->modify($clientId, $peer, $vstring), new: $peer);
    }
}

header('Location: tools.php?action=whitelist');
