<?php
authorize();

$wikiMan = new Gazelle\Manager\Wiki;
$article = $wikiMan->findById((int)$_POST['id']);
if (is_null($article)) {
    error(404);
}

if (!$article->editable($Viewer)) {
    error(403);
}

$validator = new Gazelle\Util\Validator;
$validator->setField('title', true, 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$validator->validate($_POST)) {
    error($validator->errorMessage());
}

if ($article->revision() != (int)($_POST['revision'] ?? 0)) {
    error('This article has already been modified from its original version.');
}

[$minRead, $minEdit, $error] = $wikiMan->configureAccess(
    $Viewer,
    (int)($_POST['minclassread'] ?? $article->minClassRead()),
    (int)($_POST['minclassedit'] ?? $article->minClassEdit()),
);
if ($error) {
    error($error);
}

$article->setField('Body',     trim($_POST['body']))
    ->setField('Title',        trim($_POST['title']))
    ->setField('Author',       $Viewer->id())
    ->setField('MinClassEdit', $minEdit)
    ->setField('MinClassRead', $minRead)
    ->modify();

header('Location: ' . $article->location());
