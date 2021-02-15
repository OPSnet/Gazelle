<?php

$Message = trim($_POST['message']);
$Name = trim($_POST['name']);
if (!$Message || !$Name) {
    // No message/name
    echo '-1';
    exit;
}

if (!is_numeric($_POST['id'])) {
    // No ID
    echo '-2';
    exit;
}

if ($DB->scalar("SELECT 1 FROM staff_pm_responses WHERE ID = ?", (int)$_POST['id'])) {
    // Edit response
    $DB->prepared_query("
        UPDATE staff_pm_responses SET
            Message = ?,
            Name = ?
        WHERE ID = ?
        ", $Message, $Name, $ID
    );
    echo '2';
} else {
    // Create new response
    $DB->prepared_query("
        INSERT INTO staff_pm_responses
               (Message, Name)
        VALUES (?,       ?)
        ", $Message, $Name
    );
    echo '1';
}
