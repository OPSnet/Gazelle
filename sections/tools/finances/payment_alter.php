<?php
if (!check_perms('admin_manage_payments')) {
    error(403);
}
$Payment = new \Gazelle\Manager\Payment;

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }
    $Payment->remove($_POST['id']);
} else {
    $Val->SetFields('text', '1', 'string', 'The payment text must be set, and has a max length of 100 characters', ['maxlength' => 100]);
    $Val->SetFields('rent', '1', 'number', 'Rent must be zero or positive)', ['min' => 0, 'allowperiod' => true]);
    $Val->SetFields('cc', '1', 'regex', 'The currency code must follow the ISO-4217 standard', ['regex' => '/^(XBT|EUR|USD)$/']);
    $Val->SetFields('expiry', '1', 'regex', 'The expiry must be a date in the form of YYYY-MM-DD', ['regex' => '/^\d{4}-\d{2}-\d{2}$/']);
    $Err = $Val->ValidateForm($_POST);

    if ($Err) {
        require(__DIR__ . '/payment_list.php');
        die();
    }
    $values = [
        'text'   => trim($_POST['text']),
        'expiry' => $_POST['expiry'],
        'rent'   => $_POST['rent'],
        'cc'     => $_POST['cc'],
        'active' => $_POST['active'] == 'on' ? 1 : 0,
    ];
    if ($_POST['submit'] == 'Create') {
        $Payment->create($values);
    } else {
        $Payment->modify($_POST['id'], $values);
    }
}

$Cache->delete_value('due_payments');

header('Location: tools.php?action=payment_list');
