<?php

enforce_login();

switch ($_REQUEST['action'] ?? '') {
    case 'add':
        require_once('add.php');
        break;
    case 'Remove friend':
        require_once('remove.php');
        break;
    case 'Update':
        require_once('comment.php');
        break;
    case 'Contact':
        header('Location: inbox.php?action=compose&to=' . (int)$_POST['friendid']);
        break;
    default:
        require_once('friends.php');
        break;
}
