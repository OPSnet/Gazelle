<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_site_debug')) {
    Error403::error();
}

if (isset($_POST['dbkey'])) {
    authorize();
    apcu_store('DB_KEY', hash('sha512', $_POST['dbkey']));
}

echo $Twig->render('admin/db-key.twig', [
    'viewer'      => $Viewer,
    'fingerprint' => (apcu_exists('DB_KEY') && apcu_fetch('DB_KEY'))
        ? '0x' . substr(apcu_fetch('DB_KEY'), 0, 4)
        : false,
]);
