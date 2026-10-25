<?php

declare(strict_types=1);

namespace Gazelle\Route;

class Blog extends Util\LegacyActionRoute {
    protected const array ACTIONS = [
        'deadthread'   => 'blog/dead_thread.php',
        'deleteblog'   => 'blog/delete_blog.php',
        'takeeditblog' => 'blog/edit_blog_handle.php',
        'takenewblog'  => 'blog/new_blog_handle.php',
        ''             => 'blog/blog_page.php',
    ];
}
