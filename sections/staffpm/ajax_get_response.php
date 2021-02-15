<?php

$ID = (int)$_GET['id'];
if (!$ID) {
    echo '-1';
    exit;
}

$Message = $DB->scalar("
    SELECT Message FROM staff_pm_responses WHERE ID = ?
    ", $ID
);
echo (int)$_GET['plain'] === 1 ? $Message : Text::full_format($Message);
