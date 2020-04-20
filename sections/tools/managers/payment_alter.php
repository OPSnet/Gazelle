<?php
if (!check_perms('admin_manage_payments')) {
    error(403);
}

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $DB->prepared_query("
        DELETE
        FROM payment_reminders
        WHERE ID = ?", $_POST['id']);
} else {
    $Val->SetFields('text', '1', 'string', 'The payment text must be set, and has a max length of 100 characters', ['maxlength' => 100]);
    $Val->SetFields('rent', '1', 'number', 'Rent must be zero or positive, no decimal point)', ['min' => 0, 'allowperiod' => false]);
    $Val->SetFields('expiry', '1', 'regex', 'The expiry must be a date in the form of YYYY-MM-DD', ['regex' => '/^\d{4}-\d{2}-\d{2}$/']);
    $Err = $Val->ValidateForm($_POST);

    if ($Err) {
        require(__DIR__ . '/payment_list.php');
        die();
    }

    if ($_POST['submit'] == 'Create') {
        $DB->prepared_query("
            INSERT INTO payment_reminders
                   (Text, Expiry, AnnualRent, Active)
            VALUES (?,    ?,      ?,          ?)
            ",
            $_POST['text'], $_POST['expiry'], $_POST['rent'], $_POST['active'] == 'on' ? 1 : 0);
    } else {
        if (!is_number($_POST['id']) || $_POST['id'] == '') {
            error(0);
        }

        $DB->prepared_query("
            UPDATE payment_reminders SET
                Text = ?, Expiry = ?, AnnualRent = ?, Active = ?
            WHERE ID = ?
            ", $_POST['text'], $_POST['expiry'], $_POST['rent'], $_POST['active'] == 'on' ? 1 : 0,
            $_POST['id']);
    }
}

$Cache->delete_value('due_payments');
header('Location: tools.php?action=payment_list');
