<?php

$article = (new Gazelle\Manager\Wiki)->findById((int)$_GET['id']);
if (is_null($article)) {
    error(404);
}

if (!$article->editable($Viewer)) {
    error('You do not have access to edit this article.');
}

View::show_header('Edit ' . $article->title());
echo $Twig->render('wiki/create.twig', [
    'action'     => 'edit',
    'article'    => $article,
    'body'       => new Gazelle\Util\Textarea('body', $article->body(), 92, 20),
    'class_list' => (new Gazelle\Manager\User)->classList(),
    'viewer'     => $Viewer,
]);
View::show_footer();
