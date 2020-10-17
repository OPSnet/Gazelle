<?php
enforce_login();

if (!$IsFLS) {
    // Logged in user is not FLS or Staff
    error(403);
}

$ID = (int)$_POST['id'];
if (!$ID) {
    echo '-1';
} else {
    $DB->prepared_query("
        DELETE FROM staff_pm_responses WHERE ID = ?
        ", $ID
    );
    echo '1';
}
