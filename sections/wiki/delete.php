<?php

authorize();

if (!$Viewer->permitted('admin_manage_wiki')) {
    error(403);
}

$article = (new Gazelle\Manager\Wiki())->findById((int)$_GET['id']);
if (is_null($article)) {
    error(404);
}
if (!$article->editable($Viewer)) {
    error(403);
}
if ($article->id() == INDEX_WIKI_PAGE_ID) {
    error('You cannot delete the main wiki article.');
}

(new Gazelle\Log())->general("Wiki article " . $article->id() . ' "' . $article->title() . '" was deleted by ' . $Viewer->username());
$article->remove();

header("location: wiki.php");
