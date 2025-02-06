<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

$artMan = new Gazelle\Manager\Artist();
$aliasId = (int)$_GET['aliasid'];
$artist  = $artMan->findByAliasId($aliasId);
if (is_null($artist)) {
    error(404);
} elseif ($artist->isLocked() && !$Viewer->permitted('users_mod')) {
    error('This artist is locked.');
}

if ($artist->primaryAliasId() === $aliasId) {
    error("You cannot delete the primary alias.");
}
if (!empty($artist->aliasInfo()[$aliasId]['alias'])) {
    error("This alias has redirecting aliases attached.");
}

$tgroupList = $artMan->tgroupList($aliasId, new Gazelle\Manager\TGroup());
if ($tgroupList) {
    echo $Twig->render('artist/tgroup-usage.twig', [
        'artist' => $artist,
        'list'   => $tgroupList,
    ]);
    exit;
}

$artist->removeAlias($aliasId, $Viewer);

header("Location: " . redirectUrl("artist.php?action=edit&artistid={$artist->id()}"));
