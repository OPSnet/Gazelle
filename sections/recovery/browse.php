<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_recovery')) {
    Error403::error();
}
$recovery = new Manager\Recovery();

if (isset($_POST['username']) && strlen($_POST['username'])) {
    $class = 'username';
    $target = trim($_POST['username']);
    $list = $recovery->findByUsername($target);
} elseif (isset($_POST['email']) && strlen($_POST['email'])) {
    $class = 'email';
    $target = trim($_POST['email']);
    $list = $recovery->findByEmail($target);
} elseif (isset($_POST['announce']) && strlen($_POST['announce'])) {
    $class = 'announce';
    $target = trim($_POST['announce']);
    $list = $recovery->findByAnnounce($target);
}

echo $Twig->render('recovery/browse.twig', [
    'class'  => $class ?? '',
    'list'   => $list ?? [],
    'target' => $target ?? '',
    'viewer' => $Viewer,
]);
