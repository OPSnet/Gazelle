<?php

switch ($_REQUEST['action'] ?? 'test') {
    case 'take_test':
        require_once('take_test.php');
        break;
    case 'take_upload':
        require_once('take_upload.php');
        break;
    case 'update':
        require_once('update.php');
        break;
    case 'upload':
        require_once('upload.php');
        break;
    default:
        require_once('test.php');
}
