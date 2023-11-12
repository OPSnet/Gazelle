<?php

require_once(match ($_REQUEST['action'] ?? null) {
    'get'         => 'get.php',
    'jump'        => 'jump.php',
    'take_delete' => 'delete_handle.php',
    'take_edit'   => 'edit_handle.php',
    'take_post'   => 'post_handle.php',
    'take_warn'   => 'warn_handle.php',
    'warn'        => 'warn.php',
    default       => 'comments.php',
});
