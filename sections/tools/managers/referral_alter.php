<?php
authorize();

if (!check_perms('admin_manage_referrals')) {
    error(403);
}

$ReferralManager = new Gazelle\Manager\Referral;

if ($_POST['submit'] == 'Delete') {
    $id = (int)$_POST['id'];
    if (!$id) {
        error(0);
    }
    $ReferralManager->deleteAccount($id);
} else {
    $Val->SetFields('site', '1', 'string', 'The site must be set, and has a max length of 30 characters', ['maxlength' => 30]);
    $Val->SetFields('url', '1', 'string', 'The URL must be set, and has a max length of 30 characters', ['maxlength' => 30]);
    $Val->SetFields('user', '1', 'string', 'The username must be set, and has a max length of 20 characters', ['maxlength' => 20]);
    $Val->SetFields('password', '0', 'string', 'The password must be set, and has a max length of 128 characters', ['maxlength' => 128]);
    $Val->SetFields('active', '1', 'checkbox', '');
    $Err = $Val->ValidateForm($_POST);

    if (substr($_POST['url'], -1) !== '/') {
        $_POST['url'] .= '/';
    }

    if ($_POST['submit'] === 'Create') {
        $ReferralManager->createAccount($_POST['site'], $_POST['url'], $_POST['user'], $_POST['password'],
            $_POST['active'] == 'on' ? 1 : 0, $_POST['type'], $_POST['cookie']);
    } elseif ($_POST['submit'] === 'Edit') {
        $id = (int)$_POST['id'];
        if (!$id || !$ReferralManager->getAccount($id)) {
            error(0);
        }

        $ReferralManager->updateAccount($_POST['id'], $_POST['site'], $_POST['url'], $_POST['user'],
            $_POST['password'], $_POST['active'] == 'on' ? 1 : 0, $_POST['type'], $_POST['cookie']);
    }
}

header('Location: tools.php?action=referral_accounts');
