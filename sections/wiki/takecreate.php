<?php
authorize();

$Val = new Gazelle\Util\Validator;
$Val->setField('title', '1', 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$Val->validate($_POST)) {
    error($Val->errorMessage());
}

$title = trim($_POST['title']);

$wikiMan = new Gazelle\Manager\Wiki;
$articleId = $wikiMan->findByTitle($title);
if ($articleId) {
    $error = 'An article with that name already exists <a href="wiki.php?action=article&amp;id='
        . $articleId . '">here</a>.';
}
[$minRead, $minEdit, $error] = $wikiMan->configureAccess(
    check_perms('admin_manage_wiki'),
    $LoggedUser['EffectiveClass'],
    (int)$_POST['minclassread'],
    (int)$_POST['minclassedit']
);

$ArticleID = $wikiMan->create($title, $_POST['body'], $minRead, $minEdit, $Viewer->id());
(new Gazelle\Log)->general("Wiki article $ArticleID ($title) was created by {$LoggedUser['Username']}");

header("Location: wiki.php?action=article&id=$ArticleID");
