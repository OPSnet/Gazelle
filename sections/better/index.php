<?php

switch ($_GET['method'] ?? '') {
    case 'transcode':
        require_once('transcode.php');
        break;
    default:
        require_once('better.php');
        break;
}
