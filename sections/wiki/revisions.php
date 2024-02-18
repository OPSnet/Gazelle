<?php

$wikiMan = new Gazelle\Manager\Wiki();
$article = $wikiMan->findById((int)$_GET['id']);
if (is_null($article)) {
    error(404);
}
if (!$article->readable($Viewer)) {
    error(403);
}

echo $Twig->render('wiki/revision-list.twig', [
    'article' => $article,
]);
