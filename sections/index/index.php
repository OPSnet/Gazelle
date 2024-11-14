<?php
/** @phpstan-var ?\Gazelle\User $Viewer */

if (!isset($Viewer)) {
    include 'public.php';
} else {
    if (!isset($_REQUEST['action'])) {
        include 'private.php';
    } else {
        match ($_REQUEST['action']) {
            'poll'  => include __DIR__ . '/../forums/poll_vote.php',
            default => error('Unknown action requested'),
        };
    }
}
