<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_global_notification')) {
    Error403::error();
}

$global = new Notification\GlobalNotification();
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
