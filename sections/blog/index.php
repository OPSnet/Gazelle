<?php

require_once match ($_REQUEST['action'] ?? '') {
    'deadthread'   => 'dead_thread.php',
    'deleteblog'   => 'delete_blog.php',
    'takeeditblog' => 'edit_blog_handle.php',
    'takenewblog'  => 'new_blog_handle.php',
    default        => 'blog_page.php',
};
