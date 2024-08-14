<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$request = (new Gazelle\Manager\Request())->findById((int)$_GET['id']);
if (is_null($request)) {
    error(404);
}

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'delete':
        if ($Viewer->id() != $request->userId() && !$Viewer->permitted('site_moderate_requests')) {
            error(403);
        }
        break;
    case 'unfill':
        if (!in_array($Viewer->id(), [$request->userId(), $request->fillerId()]) && !$Viewer->permitted('site_moderate_requests')) {
            error(403);
        }
        break;
    default:
        error(0);
}

echo $Twig->render('request/interim.twig', [
    'action'  => $action,
    'request' => $request,
    'viewer'  => $Viewer,
]);
