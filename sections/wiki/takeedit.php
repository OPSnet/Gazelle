<?php
authorize();

$articleId = (int)$_POST['id'];
if (!$articleId) {
    error(404);
}
$wikiMan = new Gazelle\Manager\Wiki;

$Val = new Validate;
$Val->SetFields('title', '1', 'string', 'The title must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
$error = $Val->ValidateForm($_POST);
if (!$error) {
    [$OldRevision] = $wikiMan->article($articleId);
    if (is_null($OldRevision) || $OldRevision != (int)($_POST['revision'] ?? 0)) {
        $error = 'This article has already been modified from its original version.';
    } else {
        [$minRead, $minWrite, $error] = $wikiMan->configureAccess(
            check_perms('admin_manage_wiki'),
            $LoggedUser['EffectiveClass'],
            (int)$_POST['minclassread'],
            (int)$_POST['minclassedit']
        );
    }
}
if ($error) {
    error($error);
}
$wikiMan->modify($articleId, $_POST['title'], $_POST['body'], $minRead, $minWrite, $LoggedUser['ID']);
header("Location: wiki.php?action=article&id=$articleId");
