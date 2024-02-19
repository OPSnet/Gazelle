<?php

/*-- API Start Class -------------------------------*/
/*--------------------------------------------------*/
/* Simplified version of script_start, used for the */
/* site API calls                                   */
/*--------------------------------------------------*/
/****************************************************/

// Prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

header('Expires: ' . date('D, d M Y H:i:s', time() + (2 * 60 * 60)) . ' GMT');
header('Last-Modified: ' . date('D, d M Y H:i:s') . ' GMT');
header('Content-type: application/json');

require_once(__DIR__ . '/../lib/bootstrap.php');
require_once(__DIR__ . '/../sections/api/index.php');
