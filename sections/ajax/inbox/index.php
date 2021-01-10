<?php

switch ($_GET['type'] ?? 'inbox') {
    case 'viewconv':
        require('viewconv.php');
        break;
    default:
        require('inbox.php');
        break;
}
