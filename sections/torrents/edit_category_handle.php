<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

authorize();

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('Torrent does not exist!');
}

$tgMan = new Gazelle\Manager\TGroup();
$old = $tgMan->findById((int)($_POST['oldgroupid'] ?? 0));
if (is_null($old)) {
    error('The source torrent group does not exist!');
}

$title = trim($_POST['title'] ?? '');
if ($title === '') {
    error('Title cannot be blank');
}

$newCategoryId = (int)($_POST['newcategoryid'] ?? 0);
$newName = (new Gazelle\Manager\Category())->findNameById($newCategoryId);
if (!$newName) {
    error('Bad category');
} elseif ($newName === $old->categoryName()) {
    error("Cannot change category to same category ({$newName})");
}

$new = $tgMan->changeCategory(
    old:         $old,
    torrent:     $torrent,
    categoryId:  $newCategoryId,
    artistName:  trim($_POST['artist'] ?? ''),
    name:        $title,
    releaseType: (int)($_POST['releasetype'] ?? 0),
    year:        (int)($_POST['year'] ?? 0),
    artistMan:   new Gazelle\Manager\Artist(),
    logger:      new Gazelle\Log(),
    user:        $Viewer,
);

if (is_null($new)) {
    error(0);
}
header('Location: ' . $new->location());
