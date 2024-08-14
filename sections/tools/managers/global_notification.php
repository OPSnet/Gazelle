<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_global_notification')) {
    error(403);
}

$global = new Gazelle\Notification\GlobalNotification();
if (isset($_POST['set'])) {
    $global->create($_POST['title'], $_POST['url'], $_POST['level'], (int)$_POST['length']);
} elseif (isset($_POST['delete'])) {
    $global->remove();
}

echo $Twig->render('admin/global-notification.twig', [
    'alert'     => $global->alert(),
    'level'     => $global->level(),
    'remaining' => $global->remaining(),
]);
