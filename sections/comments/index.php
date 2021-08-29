<?php

switch ($_REQUEST['action'] ?? null) {
    case 'take_delete':
        require_once('take_delete.php');
        break;
    case 'take_edit':
        require_once('take_edit.php');
        break;
    case 'take_post':
        require_once('take_post.php');
        break;
    case 'take_warn':
        require_once('take_warn.php');
        break;
    case 'get':
        require_once('get.php');
        break;
    case 'jump':
        require_once('jump.php');
        break;
    case 'warn':
        require_once('warn.php');
        break;
    default:
        require_once('comments.php');
        break;
}
