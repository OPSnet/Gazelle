<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

$tgMan = new Manager\TGroup();
$old = $tgMan->findById((int)($_POST['groupid'] ?? 0));
if (is_null($old)) {
    Error404::error();
}
$new = $tgMan->findById((int)($_POST['targetgroupid'] ?? 0));
if (is_null($new)) {
    Error400::error('Target group does not exist.');
}
if ($new->id === $old->id) {
    Error400::error('Old group ID is the same as new group ID!');
}
if ($old->categoryName() !== 'Music') {
    Error400::error('Only music groups can be merged.');
}

// Everything is legit, ask for confirmation
if (!isset($_POST['confirm'])) {
    echo $Twig->render('tgroup/confirm-merge.twig', [
        'new'    => $new,
        'old'    => $old,
        'viewer' => $Viewer,
    ]);
    return;
}

authorize();

$tgMan->merge($old, $new, $Viewer);

header("Location: {$new->location()}");
