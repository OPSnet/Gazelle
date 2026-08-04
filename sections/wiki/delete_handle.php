<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_wiki')) {
    Error403::error();
}

authorize();

$wikiMan = new Manager\Wiki();
$article = $wikiMan->findById((int)$_POST['id']);
if (is_null($article)) {
    Error404::error();
}

$id    = $article->id;
$title = $article->title();
$article->remove();
$Viewer->logger()->general(
    "Wiki article $id “{$title}” was removed by {$Viewer->username()}"
);

header('Location: wiki.php');
