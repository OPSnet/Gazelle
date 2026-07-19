<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$wikiMan = new Manager\Wiki();
$article = $wikiMan->findById((int)$_POST['id']);
if (is_null($article)) {
    Error404::error();
}

if (!$article->editable($Viewer)) {
    Error403::error();
}

$validator = new Util\Validator();
$validator->setField('title', true, 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$validator->validate($_POST)) {
    Error400::error($validator->errorMessage());
}

if ($article->revision() != (int)($_POST['revision'] ?? 0)) {
    Error400::error('This article has already been modified from its original version.');
}

[$minRead, $minEdit, $error] = $wikiMan->configureAccess(
    $Viewer,
    (int)($_POST['minclassread'] ?? $article->minClassRead()),
    (int)($_POST['minclassedit'] ?? $article->minClassEdit()),
);
if ($error) {
    Error400::error($error);
}

$article->setField('Body',     trim($_POST['body']))
    ->setField('Title',        trim($_POST['title']))
    ->setField('Author',       $Viewer->id)
    ->setField('MinClassEdit', $minEdit)
    ->setField('MinClassRead', $minRead)
    ->modify();

header('Location: ' . $article->location());
