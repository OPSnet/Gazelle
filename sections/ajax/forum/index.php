<?php

if (!empty($LoggedUser['DisableForums'])) {
    print json_die('failure');
}

// Replace the old hard-coded forum categories
$ForumCats = Forums::get_forum_categories();

switch ($_GET['type'] ?? 'main') {
    case 'main':
        require('main.php');
        break;
    case 'viewforum':
        require('forum.php');
        break;
    case 'viewthread':
        require('thread.php');
        break;
    default:
        print json_error('type');
        break;
}
