<?php

enforce_login();
$friendId = (int)$_REQUEST['friendid'];
if (!$friendId) {
    error(404);
}

switch ($_REQUEST['action'] ?? '') {
    case 'add':
        require_once('add.php');
        break;
    case 'Remove friend':
        authorize();
        require_once('remove.php');
        break;
    case 'Update':
        authorize();
        require_once('comment.php');
        break;
    case 'Contact':
        header("Location: inbox.php?action=compose&toid={$friendId}");
        break;
    default:
        require_once('friends.php');
        break;
}
