<?php

enforce_login();

require(__DIR__ . '/array.php');

if (!isset($_REQUEST['action'])) {
    require(__DIR__ . (isset($_GET['view']) ? '/static.php' : '/views.php'));
} else {
    switch ($_REQUEST['action']) {
        case 'report':
            require(__DIR__ . '/report.php');
            break;
        case 'takereport':
            require(__DIR__ . '/takereport.php');
            break;
        case 'takeresolve':
            require(__DIR__ . '/takeresolve.php');
            break;
        case 'take_pm':
            require(__DIR__ . '/take_pm.php');
            break;
        case 'search':
            require(__DIR__ . '/search.php');
            break;
        case 'new':
            require(__DIR__ . '/reports.php');
            break;
        case 'ajax_new_report':
            require(__DIR__ . '/ajax_new_report.php');
            break;
        case 'ajax_report':
            require(__DIR__ . '/ajax_report.php');
            break;
        case 'ajax_change_resolve':
            require(__DIR__ . '/ajax_change_resolve.php');
            break;
        case 'ajax_take_pm':
            require(__DIR__ . '/ajax_take_pm.php');
            break;
        case 'ajax_grab_report':
            require(__DIR__ . '/ajax_grab_report.php');
            break;
        case 'ajax_giveback_report':
            require(__DIR__ . '/ajax_giveback_report.php');
            break;
        case 'ajax_update_comment':
            require(__DIR__ . '/ajax_update_comment.php');
            break;
        case 'ajax_update_resolve':
            require(__DIR__ . '/ajax_update_resolve.php');
            break;
        case 'ajax_create_report':
            require(__DIR__ . '/ajax_create_report.php');
            break;
    }
}
