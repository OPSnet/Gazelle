<?php
enforce_login();

if (empty($_REQUEST['action'])) {
    $_REQUEST['action'] = '';
}

switch ($_REQUEST['action']) {
    case 'report':
        include('report.php');
        break;
    case 'takereport':
        include('takereport.php');
        break;
    case 'takeresolve':
        include('takeresolve.php');
        break;
    case 'stats':
        include(__DIR__ . '/stats.php');
        break;
    case 'compose':
        include(__DIR__ . '/compose.php');
        break;
    case 'takecompose':
        include(__DIR__ . '/takecompose.php');
        break;
    case 'add_notes':
        include(__DIR__ . '/ajax_add_notes.php');
        break;
    case 'claim':
        include(__DIR__ . '/ajax_claim_report.php');
        break;
    case 'unclaim':
        include(__DIR__ . '/ajax_unclaim_report.php');
        break;
    case 'resolve':
        include(__DIR__ . '/ajax_resolve_report.php');
        break;
    default:
        include(__DIR__ . '/reports.php');
        break;
}
