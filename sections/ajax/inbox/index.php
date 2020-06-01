<?php

switch ($_GET['type']) {
    case 'inbox':
    case 'sentbox':
        require('inbox.php');
        break;
    case 'viewconv':
        require('viewconv.php');
        break;
    default:
        print json_encode(['status' => 'failure']);
        break;
}
