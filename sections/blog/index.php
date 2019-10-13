<?php
enforce_login();

define('ANNOUNCEMENT_FORUM_ID', 12);

if (check_perms('admin_manage_blog')) {
    if (!empty($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case 'deadthread':
                require(SERVER_ROOT.'/sections/blog/dead_thread.php');
                break;
            case 'takeeditblog':
                require(SERVER_ROOT.'/sections/blog/take_edit_blog.php');
                break;
            case 'deleteblog':
                require(SERVER_ROOT.'/sections/blog/delete_blog.php');
                break;
            case 'takenewblog':
                require(SERVER_ROOT.'/sections/blog/take_new_blog.php');
                break;
            // Fall through as we just need to include blog_page
            case 'editblog':
            default:
                break;
        }
    }
}

require(SERVER_ROOT.'/sections/blog/blog_page.php');
