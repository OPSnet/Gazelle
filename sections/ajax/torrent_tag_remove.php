<?php

authorize();
if ($Viewer->disableTagging() || !$Viewer->permitted('site_delete_tag')) {
    error(403);
}
$tagMan = new Gazelle\Manager\Tag;
$tgMan = new Gazelle\Manager\TGroup;

$tag = $tagMan->findById((int)$_GET['tagid']);
$tgroup = $tgMan->findById((int)$_GET['groupid']);
if (is_null($tgroup) || is_null($tag)) {
    error(404);
}
$tagName = $tag->name();

if ($tgroup->removeTag($tag)) {
    $tgroup->refresh();
    $Cache->cache_value('deleted_tags_' . $tgroup->id() . '_' . $Viewer->id(), $tagName, 300);

    // Log the removal and if it was the last occurrence.
    $logger = new Gazelle\Log;
    $logger->group($tgroup, $Viewer, "Tag \"$tagName\" removed");
    if (!$tagMan->findById($tag->id())) {
        $logger->general("Unused tag \"$tagName\" removed by user {$Viewer->label()}");
    }
}
header('Location: ' . redirectUrl($tgroup->location()));
