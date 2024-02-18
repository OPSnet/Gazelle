<?php

if (!$Viewer->permitted('admin_manage_payments')) {
    error(403);
}

$Payment = new Gazelle\Manager\Payment();

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }
    $Payment->remove($_POST['id']);
} else {
    $Validator = new Gazelle\Util\Validator();
    $Validator->setFields([
        ['text', true, 'string', 'The payment text must be set, and has a max length of 100 characters', ['maxlength' => 100]],
        ['rent', true, 'number', 'Rent must be zero or positive)', ['min' => 0, 'allowperiod' => true]],
        ['cc', true, 'regex', 'The currency code must follow the ISO-4217 standard', ['regex' => '/^(XBT|EUR|USD)$/']],
        ['expiry', true, 'regex', 'The expiry must be a date in the form of YYYY-MM-DD', ['regex' => '/^\d{4}-\d{2}-\d{2}$/']],
    ]);
    if (!$Validator->validate($_POST)) {
        $Err = $Validator->errorMessage();
        require_once('payment_list.php');
        exit;
    }

    $values = [
        'text'   => trim($_POST['text']),
        'expiry' => $_POST['expiry'],
        'rent'   => $_POST['rent'],
        'cc'     => $_POST['cc'],
        'active' => isset($_POST['active']) && $_POST['active'] == 'on' ? 1 : 0,
    ];
    if ($_POST['submit'] == 'Create') {
        $Payment->create($values);
    } else {
        $Payment->modify($_POST['id'], $values);
    }
}

header('Location: tools.php?action=payment_list');
