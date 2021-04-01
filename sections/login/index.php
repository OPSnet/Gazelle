<?php

switch ($_REQUEST['action'] ?? '') {
    case 'disabled':
        require_once('disabled.php');
        break;
    case 'recover':
        require_once(isset($_REQUEST['key']) ? 'recover_step2.php' : 'recover_step1.php');
        break;
    case 'unconfirmed':
        require_once('unconfirmed.php');
        break;
    default:
        require_once('login.php');
        break;
}
