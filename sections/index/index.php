<?php

if (!isset($Viewer)) {
    require('public.php');
} else {
    if (!isset($_REQUEST['action'])) {
        require('private.php');
    } else {
        switch ($_REQUEST['action']) {
            case 'poll':
                require(__DIR__ . '/../forums/poll_vote.php');
                break;
            default:
                error(0);
        }
    }
}
