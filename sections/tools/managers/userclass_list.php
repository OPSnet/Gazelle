<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

if (isset($_REQUEST['id']) && $_REQUEST['id'] === 'new') {
    include_once 'userclass_edit.php';
    exit;
}

echo $Twig->render('admin/privilege-usage.twig', [
    'list'   => (new Gazelle\Manager\Privilege())->usageList(),
    'viewer' => $Viewer,
]);
