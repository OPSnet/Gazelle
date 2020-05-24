<?php
enforce_login();

define('ANNOUNCEMENT_FORUM_ID', 12);

if (check_perms('admin_manage_blog')) {
    switch ($_REQUEST['action'] ?? 'editblog') {
        case 'deadthread':
            require(__DIR__ . '/dead_thread.php');
            break;
        case 'takeeditblog':
            require(__DIR__ . '/take_edit_blog.php');
            break;
        case 'deleteblog':
            require(__DIR__ . '/delete_blog.php');
            break;
        case 'takenewblog':
            require(__DIR__ . '/take_new_blog.php');
            break;
        case 'editblog':
        default:
            break;
    }
}

require(__DIR__ . '/blog_page.php');
