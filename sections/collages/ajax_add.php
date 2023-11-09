<?php

header('Content-type: application/json');

if ($Viewer->auth() != $_REQUEST['auth']) {
    json_die('failure', 'auth');
}
if (!$Viewer->permitted('site_collages_manage') && !$Viewer->activePersonalCollages()) {
    json_die('failure', 'access');
}

echo (new Gazelle\Json\Ajax\CollageAdd(
    collageId:     (int)$_REQUEST['collage_id'],
    entryId:       (int)$_REQUEST['entry_id'],
    name:          trim($_REQUEST['name']),
    user:          $Viewer,
    manager:       new Gazelle\Manager\Collage,
    artistManager: new Gazelle\Manager\Artist,
    tgroupManager: new Gazelle\Manager\TGroup,
))->response();
