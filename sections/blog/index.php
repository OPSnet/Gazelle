<?php

require_once(match ($_REQUEST['action'] ?? '') {
    'deadthread'   => 'dead_thread.php',
    'deleteblog'   => 'delete_blog.php',
    'takeeditblog' => 'take_edit_blog.php',
    'takenewblog'  => 'take_new_blog.php',
    default        => 'blog_page.php',
});
