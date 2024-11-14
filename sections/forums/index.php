<?php
/** @phpstan-var \Gazelle\User $Viewer */

if ($Viewer->disableForums()) {
    error(403);
}

require_once match ($_REQUEST['action'] ?? '') {
    'add_poll_option'    => 'add_poll_option.php',
    'autosub'            => 'autosub.php',
    'catchup'            => 'catchup.php',
    'change_vote'        => 'change_vote.php',
    'delete'             => 'delete.php',
    'delete_poll_option' => 'delete_poll_option.php',
    'get_post'           => 'get_post.php',
    'mod_thread'         => 'thread_handle.php',
    'take-new'           => 'new_thread_handle.php',
    'new'                => 'new_thread.php',
    'poll_mod'           => 'poll_mod.php',
    'reply'              => 'reply_handle.php',
    'search'             => 'search.php',
    'sticky_post'        => 'sticky_post.php',
    'take_topic_notes'   => 'thread_notes_handle.php',
    'take_warn'          => 'warn_handle.php',
    'takeedit'           => 'edit_handle.php',
    'viewforum'          => 'forum.php',
    'viewthread'         => 'thread.php',
    'viewtopic'          => 'thread.php',
    'warn'               => 'warn.php',
    default              => 'main.php',
};
