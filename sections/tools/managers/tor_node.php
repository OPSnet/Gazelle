<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

$tor = new Manager\Tor();

if (isset($_POST['exitlist'])) {
    authorize();
    $tor->add($_POST['exitlist']);
}

echo $Twig->render('admin/tor_node.twig', [
    'tor'    => $tor,
    'viewer' => $Viewer,
]);
