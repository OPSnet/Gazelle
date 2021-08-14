<?php
authorize();

$article = (new Gazelle\Manager\Wiki)->findByAlias($_GET['alias'] ?? '');
if (is_null($article)) {
    error(404);
}
if (!$article->editable($Viewer)) {
    error(403);
}
$article->removeAlias($alias);
