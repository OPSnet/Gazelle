<?php

if (!isset($_REQUEST['action'])) {
    require_once(isset($_GET['view']) ? 'static.php' : 'views.php');
} else {
    switch ($_REQUEST['action']) {
        case 'report':
            require_once('report.php');
            break;
        case 'takereport':
            require_once('takereport.php');
            break;
        case 'takeresolve':
            require_once('takeresolve.php');
            break;
        case 'take_pm':
            require_once('take_pm.php');
            break;
        case 'search':
            require_once('search.php');
            break;
        case 'new':
            require_once('reports.php');
            break;
        case 'ajax_new_report':
            require_once('ajax_new_report.php');
            break;
        case 'ajax_report':
            require_once('ajax_report.php');
            break;
        case 'ajax_change_resolve':
            require_once('ajax_change_resolve.php');
            break;
        case 'ajax_take_pm':
            require_once('ajax_take_pm.php');
            break;
        case 'ajax_grab_report':
            require_once('ajax_grab_report.php');
            break;
        case 'ajax_giveback_report':
            require_once('ajax_giveback_report.php');
            break;
        case 'ajax_update_comment':
            require_once('ajax_update_comment.php');
            break;
        case 'ajax_update_resolve':
            require_once('ajax_update_resolve.php');
            break;
        case 'ajax_create_report':
            require_once('ajax_create_report.php');
            break;
    }
}
