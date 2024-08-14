<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('admin_manage_referrals')) {
    error(403);
}

authorize();

$ReferralManager = new Gazelle\Manager\Referral();

if ($_POST['submit'] == 'Delete') {
    $id = (int)$_POST['id'];
    if (!$id) {
        error(0);
    }
    $ReferralManager->deleteAccount($id);
} else {
    $Val = new Gazelle\Util\Validator();
    $Val->setFields([
        ['site', true, 'string', 'The site must be set, and has a max length of 30 characters', ['maxlength' => 30]],
        ['url', true, 'string', 'The URL must be set, and has a max length of 30 characters', ['maxlength' => 30]],
        ['user', true, 'string', 'The username must be set, and has a max length of 20 characters', ['maxlength' => 20]],
        ['password', false, 'string', 'The password must be set, and has a max length of 128 characters', ['maxlength' => 128]],
        ['active', true, 'checkbox', ''],
    ]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
    }

    if (!str_ends_with($_POST['url'], '/')) {
        $_POST['url'] .= '/';
    }

    if ($_POST['submit'] === 'Create') {
        $ReferralManager->createAccount($_POST['site'], $_POST['url'], $_POST['user'], $_POST['password'],
            $_POST['active'] == 'on', $_POST['type'], $_POST['cookie']);
    } elseif ($_POST['submit'] === 'Edit') {
        $id = (int)$_POST['id'];
        if (!$id || !$ReferralManager->getAccount($id)) {
            error(0);
        }

        $ReferralManager->updateAccount($_POST['id'], $_POST['site'], $_POST['url'], $_POST['user'],
            $_POST['password'], $_POST['active'] == 'on', $_POST['type'], $_POST['cookie']);
    }
}

header('Location: tools.php?action=referral_accounts');
