<?php
authorize();

$Val = new Validate;
$Val->SetFields('title', '1', 'string', 'The title must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
$error = $Val->ValidateForm($_POST);
$title = trim($_POST['title']);

$wikiMan = new Gazelle\Manager\Wiki;

if (!$error) {
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
}
if ($error) {
    error($error);
}

$ArticleID = $wikiMan->create($title, $_POST['body'], $minRead, $minEdit, $LoggedUser['ID']);
(new Gazelle\Log)->general("Wiki article $ArticleID ($title) was created by {$LoggedUser['Username']}");

header("Location: wiki.php?action=article&id=$ArticleID");
