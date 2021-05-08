<?php

$ArticleID = (int)$_GET['id'];
if (!$ArticleID) {
    error(404);
}
$Viewer = new Gazelle\User($LoggedUser['ID']);
$wikiMan = new Gazelle\Manager\Wiki;
[$Revision, $Title, $Body, $Read, $Edit] = $wikiMan->article($ArticleID);
if ($Edit > $Viewer->effectiveClass()) {
    error('You do not have access to edit this article.');
}

View::show_header('Edit ' . $Title);
echo $Twig->render('wiki/article.twig', [
    'action'     => 'edit',
    'body'       => new Gazelle\Util\Textarea('body', $Body, 92, 20),
    'class_list' => (new Gazelle\Manager\User)->classList(),
    'edit'       => $Edit,
    'read'       => $Read,
    'id'         => $ArticleID,
    'revision'   => $Revision,
    'title'      => $Title,
    'viewer'     => $Viewer,
]);
View::show_footer();
