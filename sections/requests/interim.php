<?php

$request = (new Gazelle\Manager\Request)->findById((int)$_GET['id']);
if (is_null($request)) {
    error(404);
}
$action = $_GET['action'];
if ($action === 'unfill') {
    if (!in_array($Viewer->id(), [$request->userId(), $request->fillerId()]) && !$Viewer->permitted('site_moderate_requests')) {
        error(403);
    }
} elseif ($action === 'delete') {
    if ($Viewer->id() != $request->userId() && !$Viewer->permitted('site_moderate_requests')) {
        error(403);
    }
} else {
    error(0);
}

View::show_header(ucwords($action) . ' Request');
echo $Twig->render('request/interim.twig', [
    'auth'     => $Viewer->auth(),
    'id'       => $request->id(),
    'action'   => $action,
]);
View::show_footer();
