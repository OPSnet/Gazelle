<?php
/** @phpstan-var \Gazelle\User $Viewer */

header('Content-type: application/json');

if ($Viewer->auth() != $_REQUEST['auth']) {
    json_die('failure', 'auth');
}
if (!$Viewer->permitted('site_collages_manage') && !$Viewer->activePersonalCollages()) {
    json_die('failure', 'access');
}
$collMan = new Gazelle\Manager\Collage();
$collage = $collMan->findById((int)($_REQUEST['collage_id'] ?? 0));
if (is_null($collage)) {
    if (preg_match(COLLAGE_REGEXP, trim($_REQUEST['name']), $match)) {
        // Looks like a URL
        $collage = $collMan->findById((int)$match['id']);
    }
    if (is_null($collage)) {
        // Must be a name of a collage
        $collage = $collMan->findByName(trim($_REQUEST['name']));
    }
    if (is_null($collage)) {
        json_die("failure", "collage not found");
    }
}
$entryManager = $collage->isArtist() ? new Gazelle\Manager\Artist() : new Gazelle\Manager\TGroup();
$entry = $entryManager->findById((int)($_REQUEST['entry_id'] ?? 0));
if (is_null($entry)) {
    json_die('failure', 'entry not found');
}

echo (new Gazelle\Json\Ajax\CollageAdd(
    collage: $collage,
    entry:   $entry,
    user:    $Viewer,
    manager: $collMan,
))->response();
