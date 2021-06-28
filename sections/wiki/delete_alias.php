<?php
authorize();

$alias = trim($_GET['alias']);
$wikiMan = new Gazelle\Manager\Wiki;
$articleId = $wikiMan->alias($alias);
if (!$wikiMan->editAllowed($articleId, $Viewer->effectiveClass())) {
    error(403);
}
$wikiMan->removeAlias($alias);
