<?php

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
