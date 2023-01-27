<?php

match ($_REQUEST['action'] ?? '') {
    'add'        => require_once('add.php'),
    'Save notes' => require_once('comment.php'),
    'Send PM'    => header('Location: inbox.php?action=compose&toid=' . (int)$_POST['friendid']),
    'Unfriend'   => require_once('remove.php'),
    default      => require_once('friends.php'),
};
