<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$wikiMan = new Manager\Wiki();
$article = $wikiMan->findById((int)$_GET['id']);
if (is_null($article)) {
    Error404::error();
}
if (!$article->readable($Viewer)) {
    Error403::error();
}

echo $Twig->render('wiki/revision-list.twig', [
    'article' => $article,
]);
