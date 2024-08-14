<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$wikiMan = new Gazelle\Manager\Wiki();
$article = $wikiMan->findById((int)$_POST['id']);
if (is_null($article)) {
    error(404);
}

if (!$Viewer->permitted('admin_manage_wiki')) {
    error(403);
}

$id    = $article->id();
$title = $article->title();
$article->remove();
(new Gazelle\Log())->general("Wiki article $id \"$title\" was removed by {$Viewer->username()}");

header('Location: wiki.php');
