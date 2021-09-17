<?php

authorize();

$request = (new Gazelle\Manager\Request)->findByid((int)$_REQUEST['id']);
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

$request->unfill($Viewer, trim($_POST['reason']));

header("Location: requests.php?action=view&id=" . $request->id());
