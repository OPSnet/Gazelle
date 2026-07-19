<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

authorize();

$torrent = new Manager\Torrent()->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error404::error('Torrent does not exist!');
}

$tgMan = new Manager\TGroup();
$old = $tgMan->findById((int)($_POST['oldgroupid'] ?? 0));
if (is_null($old)) {
    Error404::error('The source torrent group does not exist!');
}

$title = trim($_POST['title'] ?? '');
if ($title === '') {
    Error400::error('Title cannot be blank');
}

$newCategoryId = (int)($_POST['newcategoryid'] ?? 0);
$newName = new Manager\Category()->findNameById($newCategoryId);
if (!$newName) {
    Error400::error('Bad category');
} elseif ($newName === $old->categoryName()) {
    Error400::error("Cannot change category to same category ({$newName})");
}

$new = $tgMan->changeCategory(
    old:         $old,
    torrent:     $torrent,
    categoryId:  $newCategoryId,
    artistName:  trim($_POST['artist'] ?? ''),
    name:        $title,
    releaseType: (int)($_POST['releasetype'] ?? 0),
    year:        (int)($_POST['year'] ?? 0),
    artistMan:   new Manager\Artist(),
    user:        $Viewer,
);
if (is_null($new)) {
    Error400::error("Unable to change category to $newName");
}

header('Location: ' . $new->location());
