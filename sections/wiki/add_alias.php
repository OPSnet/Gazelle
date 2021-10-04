<?php
authorize();

$article = (new Gazelle\Manager\Wiki)->findById((int)$_POST['article']);
if (is_null($article)) {
    error(404);
}
if (!$article->editable($Viewer)) {
    error(403);
}

try {
    $article->addAlias(trim($_POST['alias']), $Viewer->id());
} catch (DB_MYSQL_DuplicateKeyException $e) {
    error('The alias you attempted to add is already assigned to an article.');
}

header('Location: wiki.php?action=article&id=' . $article->id());
