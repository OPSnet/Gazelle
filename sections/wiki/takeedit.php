<?php
authorize();

$ArticleID = (int)$_POST['id'];
if (!$ArticleID) {
    error(404);
}

$Val = new Validate;
$Val->SetFields('title', '1', 'string', 'The title must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
$Err = $Val->ValidateForm($_POST);
if ($Err) {
    error($Err);
}

$Article = Wiki::get_article($ArticleID);
[$OldRevision, $OldTitle, $OldBody, $CurRead, $CurEdit, $OldDate, $OldAuthor] = array_shift($Article);
if ($CurEdit > $LoggedUser['EffectiveClass']) {
    error(403);
}

if (check_perms('admin_manage_wiki')) {
    $Read = (int)$_POST['minclassread'];
    $Edit = (int)$_POST['minclassedit'];
    if (!$Read) {
        error(404);
    }
    if (!$Edit) {
        error(404);
    }
    if ($Edit > $LoggedUser['EffectiveClass']) {
        error('You cannot restrict articles above your own level.');
    }
    if ($Edit < $Read) {
        $Edit = $Read;
    }
}

$MyRevision = $_POST['revision'];
if ($MyRevision != $OldRevision) {
    error('This article has already been modified from its original version.');
}

// Store previous revision
$DB->prepared_query("
    INSERT INTO wiki_revisions
           (ID, Revision, Title, Body, Author, Date)
    VALUES (?,  ?,        ?,     ?,    ?,      ?)
    ", $ArticleID, $OldRevision, $OldTitle, $OldBody, $OldAuthor, $OldDate
);
// Update wiki entry
$field = [
    'Revision = ? + 1',
    'Title = ?',
    'Body = ?',
    'Author = ?',
];
$value = [
    $OldRevision,
    trim($_POST['title']),
    trim($_POST['body']),
    $LoggedUser['ID'],
];
if ($Read) {
    $field[] = 'MinClassRead = ?';
    $value[] = $Read;
}
if ($Edit) {
    $field[] = 'MinClassEdit = ?';
    $value[] = $Edit;
}
$value[] = $_POST['id'];

$DB->prepared_query("
    UPDATE wiki_articles SET
        Date = now(),
    " . implode(', ', $field) . "
    WHERE ID = ?
    ", ...$value
);
Wiki::flush_article($ArticleID);

header("Location: wiki.php?action=article&id=$ArticleID");
