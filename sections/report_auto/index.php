<?php

if (isset($_REQUEST['action'])) {
    require_once 'reports_handle.php';
} else {
    require_once 'reports.php';
}
