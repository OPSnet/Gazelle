<?php
authorize();

if (!check_perms('admin_manage_wiki')) {
    error(403);
}

$articleId = (int)$_GET['id'];
if (!$articleId) {
    error(404);
}

if ($articleId == INDEX_WIKI_PAGE_ID) {
    error('You cannot delete the main wiki article.');
}

$wikiMan = new Gazelle\Manager\Wiki;
if (!$wikiMan->editAllowed($articleId, $LoggedUser['EffectiveClass'])) {
    error(403);
}
[, $title] = $wikiMan->article($articleId);
if (is_null($title)) {
    error(404);
}

$wikiMan->remove($articleId);
(new Gazelle\Log)->general("Wiki article $articleId \"$title\" was deleted by {$LoggedUser['Username']}");

header("location: wiki.php");
