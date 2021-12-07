<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$tgMan = new Gazelle\Manager\TGroup;
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

$oldId = $old->id();
$oldName = $old->name();
$tgMan->merge($old, $new, $Viewer);

(new Gazelle\Log)->general("Group $oldId automatically deleted (No torrents have this group).")
    ->group($new->id(), $Viewer->id(),
        "Merged Group $oldId ($oldName) to " . $new->id() . " (" . $new->name() . ")"
    )
    ->merge($oldId, $new->id());

header('Location: ' . $new->url());
