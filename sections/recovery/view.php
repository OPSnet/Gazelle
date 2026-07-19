<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_recovery')) {
    Error403::error();
}
$recovery = new Manager\Recovery();

if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $id = (int)$_GET['id'];
    $search = false;
} elseif (isset($_GET['action']) && $_GET['action'] === 'search') {
    $search = true;
} else {
    Error404::error();
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
    if (isset($_GET['claim']) && (int)$_GET['claim'] > 0) {
        $claimId = (int)$_GET['claim'];
        if ($claimId === $Viewer->id) {
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
