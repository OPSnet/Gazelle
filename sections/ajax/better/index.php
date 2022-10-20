<?php

switch ($_GET['method'] ?? '') {
    case 'transcode':
        require_once('transcode.php');
        break;
    case 'single':
        require_once('single.php');
        break;
    case 'snatch':
    case 'artistless':
    case 'tags':
    case 'folders':
    case 'files':
    case 'upload':
    default:
        print json_encode(['status' => 'failure']);
        break;
}
