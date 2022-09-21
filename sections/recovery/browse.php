<?php

if (!$Viewer->permitted('admin_recovery')) {
    error(403);
}
$recovery = new Gazelle\Recovery;

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
