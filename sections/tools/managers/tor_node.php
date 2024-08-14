<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$tor = new Gazelle\Manager\Tor();

if (isset($_POST['exitlist'])) {
    authorize();
    $tor->add($_POST['exitlist']);
}

echo $Twig->render('admin/tor_node.twig', [
    'tor'    => $tor,
    'viewer' => $Viewer,
]);
