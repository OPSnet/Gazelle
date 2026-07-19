<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Gazelle\Cache $Cache
 */

declare(strict_types=1);

namespace Gazelle;

authorize();
if ($Viewer->disableTagging() || !$Viewer->permitted('site_delete_tag')) {
    Error403::error();
}
$tagMan = new Manager\Tag();
$tgMan = new Manager\TGroup();

$tag = $tagMan->findById((int)$_GET['tagid']);
$tgroup = $tgMan->findById((int)$_GET['groupid']);
if (is_null($tgroup) || is_null($tag)) {
    Error404::error();
}
$tagName = $tag->name();

if ($tag->removeTGroup($tgroup)) {
    $Cache->cache_value("deleted_tags_{$tgroup->id}_{$Viewer->id}", $tagName, 300);

    // Log the removal and if it was the last occurrence.
    $tag->logger()->group($tgroup, $Viewer, "Tag \"$tagName\" removed");
    if (!$tagMan->findById($tag->id)) {
        $tag->logger()->general("Unused tag \"$tagName\" removed by user {$Viewer->label()}");
    }
}
header('Location: ' . redirectUrl($tgroup->location()));
