<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$tgMan = new Gazelle\Manager\TGroup();
$old = $tgMan->findById((int)($_POST['groupid'] ?? 0));
if (is_null($old)) {
    error(404);
}
$new = $tgMan->findById((int)($_POST['targetgroupid'] ?? 0));
if (is_null($new)) {
    error('Target group does not exist.');
}
if ($new->id() === $old->id()) {
    error('Old group ID is the same as new group ID!');
}
if ($old->categoryName() !== 'Music') {
    error('Only music groups can be merged.');
}

// Everything is legit, ask for confirmation
if (empty($_POST['confirm'])) {
    echo $Twig->render('torrent/confirm-merge.twig', [
        'auth' => $Viewer->auth(),
        'new'  => $new,
        'old'  => $old,
    ]);
    exit;
}

authorize();

$tgMan->merge(
    $old,
    $new,
    $Viewer,
    new \Gazelle\Manager\User(),
    new \Gazelle\Manager\Vote(),
);

header('Location: ' . $new->location());
