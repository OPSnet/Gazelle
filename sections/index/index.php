<?php
/** @phpstan-var ?\Gazelle\User $Viewer */

if (!isset($Viewer)) {
    require('public.php');
} else {
    if (!isset($_REQUEST['action'])) {
        require('private.php');
    } else {
        match ($_REQUEST['action']) {
            'poll' => require(__DIR__ . '/../forums/poll_vote.php'),
            default => error(0),
        };
    }
}
