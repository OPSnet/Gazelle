<?php

require_once(match ($_REQUEST['action'] ?? null) {
    'get'         => 'get.php',
    'jump'        => 'jump.php',
    'take_delete' => 'take_delete.php',
    'take_edit'   => 'take_edit.php',
    'take_post'   => 'take_post.php',
    'take_warn'   => 'take_warn.php',
    'warn'        => 'warn.php',
    default       => 'comments.php',
});
