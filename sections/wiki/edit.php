<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$article = new Manager\Wiki()->findById((int)$_GET['id']);
if (is_null($article)) {
    Error404::error();
}

if (!$article->editable($Viewer)) {
    Error403::error('You do not have access to edit this article.');
}

echo $Twig->render('wiki/create.twig', [
    'action'     => 'edit',
    'article'    => $article,
    'body'       => new Util\Textarea('body', $article->body(), 92, 20),
    'class_list' => new Manager\User()->classList(),
    'viewer'     => $Viewer,
]);
