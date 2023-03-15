<?php

authorize();

$request = (new Gazelle\Manager\Request)->findById((int)$_REQUEST['id']);
if (is_null($request)) {
    error(404);
}
if ($request->fillerId() === 0
    || (
        !in_array($Viewer->id(), [$request->userId(), $request->fillerId()])
        && !$Viewer->permitted('site_moderate_requests')
    )
) {
    error(403);
}

$request->unfill($Viewer, trim($_POST['reason']), new Gazelle\Manager\Torrent);

header('Location: ' . $request->location());
