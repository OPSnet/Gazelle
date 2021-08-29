<?php

switch ($_REQUEST['action'] ?? '') {
    case 'add':
        require_once('add.php');
        break;
    case 'Unfriend':
        require_once('remove.php');
        break;
    case 'Save notes':
        require_once('comment.php');
        break;
    case 'Send PM':
        header('Location: inbox.php?action=compose&toid=' . (int)$_POST['friendid']);
        break;
    default:
        require_once('friends.php');
        break;
}
