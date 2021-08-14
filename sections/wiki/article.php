<?php

$wikiMan = new Gazelle\Manager\Wiki;
$article = false;
$error = false;
if (isset($_GET['id'])) {
    $article = $wikiMan->findById((int)$_GET['id']);
    if (is_null($article)) {
        $error = 'No such wiki article found';
    }
} elseif (isset($_GET['name'])) {
    $article = $wikiMan->findByAlias($_GET['name']);
    if (is_null($article)) {
        $error = 'No such wiki article with that name found';
    }
}
if (!$article) {
    $article = $wikiMan->findById(INDEX_WIKI_PAGE_ID);
}

if (!$article->readable($Viewer)) {
    error(403);
}
$classList = (new Gazelle\Manager\User)->classLevelList();

View::show_header($article->title(), ['js' => 'wiki,bbcode']);
echo $Twig->render('wiki/article.twig', [
    'article' => $article,
    'edit'    => $classList[$article->minClassEdit()]['Name'],
    'read'    => $classList[$article->minClassRead()]['Name'],
    'error'   => $error,
    'viewer'  => $Viewer,
]);
View::show_footer();
