<?php
/** @phpstan-var \Gazelle\User $Viewer */

// Remove 2FA. Users have to enter their password, moderators skip this step.
$user = (new Gazelle\Manager\User())->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
if (!$user->TFAKey()) {
    error($Viewer->permitted('users_edit_password') ? 'No 2FA configured' : 404);
}

$userId = $user->id();
if (!$Viewer->permitted('users_edit_password')) {
    if ($userId !== $Viewer->id()) {
        error(403);
    } elseif (empty($_POST['password'])) {
        include_once 'confirm.php';
        exit;
    } elseif (!$user->validatePassword($_POST['password'])) {
        header('Location: user.php?action=2fa&do=confirm=invalid&userid=' . $userId);
        exit;
    }
}
$user->remove2FA()->modify();

header("Location: {$user->location()}");
