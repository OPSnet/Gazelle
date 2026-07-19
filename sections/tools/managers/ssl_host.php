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

$ssl = new Manager\SSLHost();

$remove = array_key_extract_suffix('id-', $_POST);
if ($remove) {
    authorize();
    $ssl->removeList($remove);
}
if (!empty($_POST['hostname']) && !empty($_POST['port'])) {
    authorize();
    $ssl->add(trim($_POST['hostname']), (int)$_POST['port']);
}

echo $Twig->render('admin/ssl_host.twig', [
    'list'   => $ssl->list(),
    'viewer' => $Viewer,
]);
