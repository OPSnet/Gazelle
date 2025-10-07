<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

header('Content-type: application/json');

if ($Viewer->auth() != $_REQUEST['auth']) {
    json_die('failure', 'auth');
}
if (!$Viewer->permitted('site_collages_manage') && !$Viewer->activePersonalCollages()) {
    json_die('failure', 'access');
}
$collMan = new Manager\Collage();
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
$entryManager = $collage->isArtist() ? new Manager\Artist() : new Manager\TGroup();
$entry = $entryManager->findById((int)($_REQUEST['entry_id'] ?? 0));
if (is_null($entry)) {
    json_die('failure', 'entry not found');
}

echo new Json\Ajax\CollageAdd($collage, $entry, $Viewer)
    ->response();
