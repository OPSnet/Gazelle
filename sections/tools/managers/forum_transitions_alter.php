<?php

use Gazelle\Util\Arrays;

authorize();

if (!check_perms('admin_manage_forums')) {
    error(403);
}

$P = Arrays::trim($_POST);

if ($_POST['submit'] === 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $DB->prepared_query("DELETE FROM forums_transitions WHERE forums_transitions_id = ?", $P['id']);
} else {
    $Val->SetFields('source', true, 'number', 'You must set a source forum ID for the transition');
    $Val->SetFields('destination', true, 'number', 'You must set a destination forum ID for the transition');
    $Val->SetFields('label', true, 'string', 'The button label must be set, and has a max length of 20 characters', ['maxlength' => 20]);
    $Val->SetFields('permissions', false, 'string', 'The permissions have a max length of 50 characters', ['maxlength' => 50]);
    $Err = $Val->ValidateForm($_POST);

    if ($Err) {
        error($Err);
    }

    if (empty($P['permissions'])) {
        $P['permissions'] = '';
    }
    if ($_POST['submit'] === 'Create') {
        $DB->prepared_query("
            INSERT INTO forums_transitions (source, destination, label, permission_levels)
            VALUES                         (?,      ?,           ?,     ?)",
            $P['source'], $P['destination'], $P['label'], $P['permissions']);
    } elseif ($_POST['submit'] === 'Edit') {
        if (!is_number($_POST['id']) || $_POST['id'] == '') {
            error(0);
        }

        $DB->prepared_query("
            UPDATE forums_transitions
                SET source = ?,
                    destination = ?,
                    label = ?,
                    permission_levels = ?
            WHERE forums_transitions_id = ?",
            $P['source'], $P['destination'], $P['label'], $P['permissions'], $P['id']);
    }
}

$Cache->delete_value('forum_transitions');
header('Location: tools.php?action=forum_transitions');
