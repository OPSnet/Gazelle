<?php
authorize();

$wikiMan = new Gazelle\Manager\Wiki;
$article = $wikiMan->findById((int)$_POST['id']);
if (is_null($article)) {
    error(404);
}

$validator = new Gazelle\Util\Validator;
$validator->setField('title', '1', 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$validator->validate($_POST)) {
    error($validator->errorMessage());
}

if ($article->revision() != (int)($_POST['revision'] ?? 0)) {
    error('This article has already been modified from its original version.');
}
[$minRead, $minEdit, $error] = $wikiMan->configureAccess(
    $Viewer, (int)$_POST['minclassread'], (int)$_POST['minclassedit']
);
if ($error) {
    error($error);
}

$article->setUpdate('Body', trim($_POST['body']))
    ->setUpdate('Title', trim($_POST['title']))
    ->setUpdate('Author', $Viewer->id())
    ->setUpdate('MinClassEdit', $minEdit)
    ->setUpdate('MinClassRead', $minRead)
    ->modify();

header('Location: ' . $article->url());
