<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . '/' . match ($_REQUEST['action'] ?? null) {
    'compose'     => 'compose.php',
    'forward'     => 'forward.php',
    'get_post'    => 'get_post.php',
    'masschange'  => 'massdelete_handle.php',
    'takecompose' => 'compose_handle.php',
    'takeedit'    => 'edit_handle.php',
    'viewconv'    => 'conversation.php',
    default       => 'inbox.php',
};
