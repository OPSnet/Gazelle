<?php

switch ($_GET['type'] ?? '') {
    case 'posts':
        require_once('post_history.php');
        break;
    default:
        print json_die('bad type');
        break;
}
