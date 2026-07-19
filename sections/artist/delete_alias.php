<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}
authorize();

$artMan = new Manager\Artist();
$aliasId = (int)$_GET['aliasid'];
$artist  = $artMan->findByAliasId($aliasId);
if (is_null($artist)) {
    Error404::error();
} elseif ($artist->isLocked() && !$Viewer->permitted('users_mod')) {
    Error400::error('This artist is locked.');
}

if ($artist->primaryAliasId() === $aliasId) {
    Error400::error("You cannot delete the primary alias.");
}
if (!empty($artist->aliasInfo()[$aliasId]['alias'])) {
    Error400::error("This alias has redirecting aliases attached.");
}

$tgroupList = $artMan->tgroupList($aliasId, new Manager\TGroup());
if ($tgroupList) {
    echo $Twig->render('artist/tgroup-usage.twig', [
        'artist' => $artist,
        'list'   => $tgroupList,
    ]);
    exit;
}

$artist->removeAlias($aliasId);

header("Location: " . redirectUrl("artist.php?action=edit&artistid={$artist->id}"));
