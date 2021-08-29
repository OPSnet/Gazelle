<?php

switch ($_GET['method'] ?? '') {
    case 'missing':
        require_once('missing.php');
        break;
    case 'single':
        require_once('single.php');
        break;
    default:
        require_once('transcode.php');
        break;
}
