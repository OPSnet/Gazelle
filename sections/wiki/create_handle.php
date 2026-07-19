<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$validator = new Util\Validator();
$validator->setField('title', true, 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$validator->validate($_POST)) {
    Error400::error($validator->errorMessage());
}

$wikiMan = new Manager\Wiki();
$title = trim($_POST['title']);
$article = $wikiMan->findByTitle($title);
if ($article) {
    Error400::error(
        'An article with that name already exists <a href="{$article->url()}">here</a>'
    );
}
[$minRead, $minEdit, $error] = $wikiMan->configureAccess(
    $Viewer, (int)$_POST['minclassread'], (int)$_POST['minclassedit']
);
if ($error) {
    Error400::error($error);
}

$article = $wikiMan->create($title, $_POST['body'], $minRead, $minEdit, $Viewer);
$article->logger()->general(
    "Wiki article {$article->id} \"$title\" was created by {$Viewer->username()}"
);

header('Location: ' . $article->location());
