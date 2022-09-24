<?php

authorize();

$alias = $_GET['alias'] ?? '';
$article = (new Gazelle\Manager\Wiki)->findByAlias($alias);
if (is_null($article)) {
    error(404);
}

if (!$article->editable($Viewer)) {
    error(403);
}

$article->removeAlias($alias);
