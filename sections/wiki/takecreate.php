<?php
authorize();

$Val = new Validate;
$Val->SetFields('title', '1', 'string', 'The title must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
$Err = $Val->ValidateForm($_POST);
$title = trim($_POST['title']);

if (!$Err) {
    $articleId = $DB->scalar("
        SELECT ID
        FROM wiki_articles
        WHERE Title = ?
        ", $title
    );
    if ($articleId) {
        $Err = 'An article with that name already exists <a href="wiki.php?action=article&amp;id='
            . $articleId . '">here</a>.';
    }
}
if ($Err) {
    error($Err);
}

if (!check_perms('admin_manage_wiki')) {
    $Read = 100;
    $Edit = 100;
} else {
    $Read = (int)$_POST['minclassread'];
    $Edit = (int)$_POST['minclassedit'];
    if (!$Read) {
        error(404);
    }
    if (!$Edit) {
        error(404);
    }
    if ($Edit > $LoggedUser['EffectiveClass']) {
        error('You cannot restrict articles above your own level');
    }
    if ($Edit < $Read) {
        $Edit = $Read;
    }
}

$DB->prepared_query("
    INSERT INTO wiki_articles
           (Title, Body, MinClassRead, MinClassEdit, Author, Date, Revision)
    VALUES (?,     ?,    ?,            ?,            ?,      now(), 1)
    ", $title, trim($_POST['body']), $Read, $Edit, $LoggedUser['ID']
);
$ArticleID = $DB->inserted_id();

$TitleAlias = Wiki::normalize_alias($_POST['title']);
$Dupe = Wiki::alias_to_id($_POST['title']);
if ($TitleAlias != '' && $Dupe === false) {
    $DB->prepared_query("
        INSERT INTO wiki_aliases
               (ArticleID, Alias)
        VALUES (?,         ?)
        ", $ArticleID, $TitleAlias
    );
    Wiki::flush_aliases();
}

(new Gazelle\Log)->general("Wiki article $ArticleID (".$_POST['title'].") was created by ".$LoggedUser['Username']);

header("Location: wiki.php?action=article&id=$ArticleID");
