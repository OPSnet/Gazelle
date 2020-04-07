<?php

enforce_login();

include(__DIR__ . '/array.php');

if (!isset($_REQUEST['action'])) {
    include(__DIR__ . (isset($_GET['view']) ? '/static.php' : '/views.php'));
} else {
    switch ($_REQUEST['action']) {
        case 'report':
            include(__DIR__ . '/report.php');
            break;
        case 'takereport':
            include(__DIR__ . '/takereport.php');
            break;
        case 'takeresolve':
            include(__DIR__ . '/takeresolve.php');
            break;
        case 'take_pm':
            include(__DIR__ . '/take_pm.php');
            break;
        case 'search':
            include(__DIR__ . '/search.php');
            break;
        case 'new':
            include(__DIR__ . '/reports.php');
            break;
        case 'ajax_new_report':
            include(__DIR__ . '/ajax_new_report.php');
            break;
        case 'ajax_report':
            include(__DIR__ . '/ajax_report.php');
            break;
        case 'ajax_change_resolve':
            include(__DIR__ . '/ajax_change_resolve.php');
            break;
        case 'ajax_take_pm':
            include(__DIR__ . '/ajax_take_pm.php');
            break;
        case 'ajax_grab_report':
            include(__DIR__ . '/ajax_grab_report.php');
            break;
        case 'ajax_giveback_report':
            include(__DIR__ . '/ajax_giveback_report.php');
            break;
        case 'ajax_update_comment':
            include(__DIR__ . '/ajax_update_comment.php');
            break;
        case 'ajax_update_resolve':
            include(__DIR__ . '/ajax_update_resolve.php');
            break;
        case 'ajax_create_report':
            include(__DIR__ . '/ajax_create_report.php');
            break;
    }
}
