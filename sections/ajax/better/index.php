<?php

switch ($_GET['method'] ?? '') {
    case 'transcode':
        require_once('transcode.php');
        break;
    case 'single':
        require_once('single.php');
        break;
    case 'snatch':
        require_once('snatch.php');
        break;
    case 'artistless':
        require_once('artistless.php');
        break;
    case 'tags':
        require_once('tags.php');
        break;
    case 'folders':
        require_once('folders.php');
        break;
    case 'files':
        require_once('files.php');
        break;
    case 'upload':
        require_once('upload.php');
        break;
    default:
        print json_encode(['status' => 'failure']);
        break;
}
