<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
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
