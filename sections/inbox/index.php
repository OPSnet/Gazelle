<?php

require_once(match ($_REQUEST['action'] ?? '') {
    'takecompose' => 'takecompose.php',
    'takeedit'    => 'takeedit.php',
    'compose'     => 'compose.php',
    'viewconv'    => 'conversation.php',
    'masschange'  => 'massdelete_handle.php',
    'get_post'    => 'get_post.php',
    'forward'     => 'forward.php',
    default       => 'inbox.php',
});
