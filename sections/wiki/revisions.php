<?php

$wikiMan = new Gazelle\Manager\Wiki;
$article = $wikiMan->findById((int)$_GET['id']);
if (is_null($article)) {
    error(404);
}
if (!$article->readable($Viewer)) {
    error(403);
}

View::show_header("Revisions of " . $article->title());
echo $Twig->render('wiki/revision-list.twig', [
    'article' => $article,
]);
View::show_footer();
