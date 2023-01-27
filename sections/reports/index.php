<?php

require_once(match ($_REQUEST['action'] ?? '') {
    'add_notes'   => 'ajax_add_notes.php',
    'claim'       => 'ajax_claim_report.php',
    'compose'     => 'compose.php',
    'report'      => 'report.php',
    'resolve'     => 'ajax_resolve_report.php',
    'stats'       => 'stats.php',
    'takecompose' => 'takecompose.php',
    'takereport'  => 'takereport.php',
    'takeresolve' => 'takeresolve.php',
    'unclaim'     => 'ajax_unclaim_report.php',
    default       => 'reports.php',
});
