<?php

// Remove 2FA. Users have to enter their password, moderators skip this step.
$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
$userId = $user->id();

if (!check_perms('users_edit_password')) {
    if ($userId !== $Viewer->id()) {
        error(403);
    } elseif (empty($_POST['password'])) {
        require_once('confirm.php');
        exit;
    } elseif (!$user->validatePassword($_POST['password'])) {
        header('Location: user.php?action=2fa&do=confirm&invalid&userid=' . $userId);
        exit;
    }
}
$user->remove2FA()->modify();

header('Location: user.php?userid=' . $userId);
