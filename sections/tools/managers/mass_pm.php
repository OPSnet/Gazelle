<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted("admin_global_notification")) {
    error(404);
}

echo $Twig->render('admin/mass-pm.twig', [
    'body'   => new Gazelle\Util\Textarea('body', '', 95, 10),
    'class'  => (new Gazelle\Manager\User())->classList(),
    'viewer' => $Viewer,
]);
