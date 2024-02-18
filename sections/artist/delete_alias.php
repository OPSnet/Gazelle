<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

$artMan = new Gazelle\Manager\Artist();
$aliasId = (int)$_GET['aliasid'];
$artist  = $artMan->findByAliasId($aliasId);
if (is_null($artist)) {
    error(404);
}

if ($artMan->aliasUseTotal($aliasId) == 1) {
    error("The alias $aliasId is the only alias for this artist; removing it would cause bad things to happen.");
}

$tgroupList = $artMan->tgroupList($aliasId, new Gazelle\Manager\TGroup());
if ($tgroupList) {
    echo $Twig->render('artist/tgroup-usage.twig', [
        'artist' => $artist,
        'list'   => $tgroupList,
    ]);
    exit;
}

$artist->removeAlias($aliasId, $Viewer, new Gazelle\Log());

header("Location: " . redirectUrl("artist.php?action=edit&artistid={$artist->id()}"));
