<?php

if (!$Viewer->permitted('admin_recovery')) {
    error(403);
}
$recovery = new Gazelle\Manager\Recovery();

if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $id = (int)$_GET['id'];
    $search = false;
} elseif (isset($_GET['action']) && $_GET['action'] == 'search') {
    $search = true;
} else {
    error(404);
}

$terms = [];
if ($search) {
    foreach (['token', 'username', 'email', 'announce'] as $key) {
        if (isset($_GET[$key])) {
            $terms[] = [$key => $_GET[$key]];
        }
    }
    $info = $recovery->search($terms);
    $id = $info['recovery_id'];
} else {
    if (isset($_GET['claim']) and (int)$_GET['claim'] > 0) {
        $claimId = (int)$_GET['claim'];
        if ($claimId == $Viewer->id()) {
            $recovery->claim($id, $claimId, $Viewer->username());
        }
    }
    $info = $recovery->info($id);
}

$enabled = ['Unconfirmed', 'Enabled', 'Disabled'];

echo $Twig->render('recovery/view.twig', [
    'candidate' => $recovery->findCandidate($info['username']),
    'id'        => $id,
    'info'      => $info,
    'terms'     => $terms,
    'viewer'    => $Viewer,
]);
