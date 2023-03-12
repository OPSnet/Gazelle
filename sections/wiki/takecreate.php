<?php
authorize();

$validator = new Gazelle\Util\Validator;
$validator->setField('title', true, 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$validator->validate($_POST)) {
    error($validator->errorMessage());
}

$wikiMan = new Gazelle\Manager\Wiki;
$title = trim($_POST['title']);
$article = $wikiMan->findByTitle($title);
if ($article) {
    error('An article with that name already exists <a href="wiki.php?action=article&amp;id='
        . $article->id() . '">here</a>'
    );
}
[$minRead, $minEdit, $error] = $wikiMan->configureAccess(
    $Viewer, (int)$_POST['minclassread'], (int)$_POST['minclassedit']
);
if ($error) {
    error($error);
}

$article = $wikiMan->create($title, $_POST['body'], $minRead, $minEdit, $Viewer->id());
(new Gazelle\Log)->general("Wiki article " . $article->id() . "\"$title\" was created by " . $Viewer->username());

header('Location: ' . $article->location());
