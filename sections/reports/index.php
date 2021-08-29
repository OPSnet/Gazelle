<?php

switch ($_REQUEST['action'] ?? '') {
    case 'report':
        require_once('report.php');
        break;
    case 'takereport':
        require_once('takereport.php');
        break;
    case 'takeresolve':
        require_once('takeresolve.php');
        break;
    case 'stats':
        require_once('stats.php');
        break;
    case 'compose':
        require_once('compose.php');
        break;
    case 'takecompose':
        require_once('takecompose.php');
        break;
    case 'add_notes':
        require_once('ajax_add_notes.php');
        break;
    case 'claim':
        require_once('ajax_claim_report.php');
        break;
    case 'unclaim':
        require_once('ajax_unclaim_report.php');
        break;
    case 'resolve':
        require_once('ajax_resolve_report.php');
        break;
    default:
        require_once('reports.php');
        break;
}
