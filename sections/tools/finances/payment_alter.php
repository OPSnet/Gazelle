<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_payments')) {
    Error403::error();
}

$Payment = new Manager\Payment();

if ($_POST['submit'] === 'Delete') {
    if ($Payment->remove((int)$_POST['id']) < 1) {
        Error404::error('Unknown payment id');
    }
} else {
    $Validator = new Util\Validator();
    $Validator->setFields([
        ['text', true, 'string', 'The payment text must be set, and has a max length of 100 characters', ['maxlength' => 100]],
        ['rent', true, 'number', 'Rent must be zero or positive)', ['min' => 0, 'allowperiod' => true]],
        ['cc', true, 'regex', 'The currency code must follow the ISO-4217 standard', ['regex' => '/^(XBT|EUR|USD)$/']],
        ['expiry', true, 'regex', 'The expiry must be a date in the form of YYYY-MM-DD', ['regex' => '/^\d{4}-\d{2}-\d{2}$/']],
    ]);
    if (!$Validator->validate($_POST)) {
        $Err = $Validator->errorMessage();
        require __DIR__ . '/payment_list.php';
        exit;
    }

    if ($_POST['submit'] === 'Create') {
        $Payment->create(
            trim($_POST['text']),
            $_POST['expiry'],
            (float)$_POST['rent'],
            $_POST['cc'],
            isset($_POST['active']),
        );
    } else {
        $Payment->modify(
            (int)$_POST['id'],
            trim($_POST['text']),
            $_POST['expiry'],
            (float)$_POST['rent'],
            $_POST['cc'],
            isset($_POST['active']),
        );
    }
}

header('Location: tools.php?action=payment_list');
