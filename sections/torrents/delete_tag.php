<?php

authorize();
if ($Viewer->disableTagging() || !$Viewer->permitted('site_delete_tag')) {
    error(403);
}
$tagMan = new Gazelle\Manager\Tag;
$tgroupMan = new Gazelle\Manager\TGroup;

$tag = $tagMan->findById((int)$_GET['tagid']);
$tgroup = $tgroupMan->findById((int)$_GET['groupid']);
if (is_null($tgroup) || is_null($tag)) {
    error(404);
}

if ($tgroup->removeTag($tag)) {
    $tgroupMan->refresh($tgroup->id());
    $Cache->cache_value('deleted_tags_' . $tgroup->id() . '_' . $Viewer->id(), $name, 300);

    // Log the removal and if it was the last occurrence.
    $logger = new Gazelle\Log;
    $logger->group($tgroup->id(), $Viewer->id(), "Tag \"" . $tag->name() . "\" removed from group " . $tgroup->id());
    if (!$tagMan->findById($tag->id())) {
        $logger->general("Unused tag \"" . $tag->name() . "\" removed by user " . $Viewer->label());
    }
}
header('Location: ' . redirectUrl($tgroup->url()));
