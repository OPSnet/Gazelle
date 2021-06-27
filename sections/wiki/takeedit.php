<?php
authorize();

$articleId = (int)$_POST['id'];
if (!$articleId) {
    error(404);
}
$wikiMan = new Gazelle\Manager\Wiki;

$Val = new Gazelle\Util\Validator;
$Val->setField('title', '1', 'string', 'The title must be between 3 and 100 characters', ['range' => [3, 100]]);
if (!$Val->validate($_POST)) {
    error($Val->errorMessage());
}

[$OldRevision] = $wikiMan->article($articleId);
if (is_null($OldRevision) || $OldRevision != (int)($_POST['revision'] ?? 0)) {
    error('This article has already been modified from its original version.');
}
[$minRead, $minWrite, $error] = $wikiMan->configureAccess(
    check_perms('admin_manage_wiki'),
    $LoggedUser['EffectiveClass'],
    (int)$_POST['minclassread'],
    (int)$_POST['minclassedit']
);

$wikiMan->modify($articleId, $_POST['title'], $_POST['body'], $minRead, $minWrite, $Viewer->id());
header("Location: wiki.php?action=article&id=$articleId");
