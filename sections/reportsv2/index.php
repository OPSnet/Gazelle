<?php

require_once(match ($_REQUEST['action'] ?? '') {
    'ajax_change_resolve' => 'ajax_change_resolve.php',
    'ajax_claim'          => 'ajax_claim.php',
    'ajax_new_report'     => 'ajax_new_report.php',
    'ajax_report'         => 'ajax_report.php',
    'ajax_switch'         => 'ajax_switch.php',
    'ajax_take_pm'        => 'ajax_take_pm.php',
    'ajax_unclaim'        => 'ajax_unclaim.php',
    'ajax_update_comment' => 'ajax_update_comment.php',
    'ajax_update_resolve' => 'ajax_update_resolve.php',
    'new'                 => 'reports.php',
    'report'              => 'report.php',
    'search'              => 'search.php',
    'takereport'          => 'report_handle.php',
    'takeresolve'         => 'resolve_handle.php',
    default               => isset($_GET['view']) ? 'static.php' : 'views.php',
});
