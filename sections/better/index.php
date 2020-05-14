<?php

enforce_login();

switch ($_GET['method'] ?? '') {
    case 'missing':
        require(__DIR__ . '/missing.php');
        break;
    case 'single':
        require(__DIR__ . '/single.php');
        break;
    case 'transcode':
    default:
        require(__DIR__ . '/transcode.php');
        break;
}
