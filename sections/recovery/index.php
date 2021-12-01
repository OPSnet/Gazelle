<?php

switch ($_GET['action'] ?? '') {
    case 'save':
        require_once(RECOVERY ? 'save.php' : 'recover.php');
        break;
    case 'admin':
        require_once('admin.php');
        break;
    case 'browse':
        require_once('browse.php');
        break;
    case 'pair':
        require_once('pair.php');
        break;
    case 'search':
    case 'view':
        require_once('view.php');
        break;
    default:
        require_once('recover.php');
        break;
}
