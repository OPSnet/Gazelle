<?php

define('ANNOUNCEMENT_FORUM_ID', 12);

switch ($_REQUEST['action'] ?? '') {
    case 'deadthread':
        require_once('dead_thread.php');
        break;
    case 'takeeditblog':
        require_once('take_edit_blog.php');
        break;
    case 'deleteblog':
        require_once('delete_blog.php');
        break;
    case 'takenewblog':
        require_once('take_new_blog.php');
        break;
    case 'editblog':
    default:
        require_once('blog_page.php');
        break;
}
