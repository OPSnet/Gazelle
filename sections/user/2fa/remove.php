<?php

// Remove 2FA. Users have to enter their password, moderators skip this step.

if ($userId !== $LoggedUser['ID'] && !check_perms('users_edit_password')) {
    error(403);
} elseif (empty($_POST['password'])) {
    require_once('confirm.php');
    exit;
} elseif (!$user->validatePassword($_POST['password'])) {
    header('Location: user.php?action=2fa&do=confirm&invalid&userid=' . $userId);
    exit;
}
$user->remove2FA()->modify();

if (!isset($_GET['page']) || $_GET['page'] !== 'user') {
    $action = 'action=edit&';
} else {
    $action = '';
}
header('Location: user.php?' . $action . 'userid=' . $userId);
