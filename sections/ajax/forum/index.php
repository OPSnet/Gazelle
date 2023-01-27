<?php

if ($Viewer->disableForums()) {
    print json_die('failure');
}

match ($_GET['type'] ?? 'main') {
    'main'       => require_once('main.php'),
    'viewforum'  => require_once('forum.php'),
    'viewthread' => require_once('thread.php'),
    default      => json_error('type'),
};
