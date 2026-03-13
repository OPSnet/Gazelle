<?php

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . match ($_REQUEST['action'] ?? '') {
    'deadthread'   => '/dead_thread.php',
    'deleteblog'   => '/delete_blog.php',
    'takeeditblog' => '/edit_blog_handle.php',
    'takenewblog'  => '/new_blog_handle.php',
    default        => '/blog_page.php',
};
