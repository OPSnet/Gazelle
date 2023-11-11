<?php

require_once(match ($_REQUEST['action'] ?? '') {
    'compose'     => 'compose.php',
    'forward'     => 'forward.php',
    'get_post'    => 'get_post.php',
    'masschange'  => 'take_massdelete.php',
    'takecompose' => 'take_compose.php',
    'takeedit'    => 'take_edit.php',
    'viewconv'    => 'conversation.php',
    default       => 'inbox.php',
});
