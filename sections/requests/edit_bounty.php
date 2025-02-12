<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_admin_requests')) {
    error(403);
}

$request = (new Gazelle\Manager\Request())->findById((int)$_GET['id']);
if (is_null($request)) {
    error(404);
}

echo $Twig->render('request/edit-bounty.twig', [
    'request' => $request,
    'viewer'  => $Viewer,
]);
