<?php

if ($Viewer->disableForums()) {
    error(403);
}

match ($_REQUEST['action'] ?? '') {
    'add_poll_option'    => require_once('add_poll_option.php'),
    'autosub'            => require_once('autosub.php'),
    'catchup'            => require_once('catchup.php'),
    'change_vote'        => require_once('change_vote.php'),
    'delete'             => require_once('delete.php'),
    'delete_poll_option' => require_once('delete_poll_option.php'),
    'get_post'           => require_once('get_post.php'),
    'mod_thread'         => require_once('thread_handle.php'),
    'take-new'           => require_once('new_thread_handle.php'),
    'new'                => require_once('new_thread.php'),
    'poll_mod'           => require_once('poll_mod.php'),
    'reply'              => require_once('reply_handle.php'),
    'search'             => require_once('search.php'),
    'sticky_post'        => require_once('sticky_post.php'),
    'take_topic_notes'   => require_once('thread_notes_handle.php'),
    'take_warn'          => require_once('warn_handle.php'),
    'takeedit'           => require_once('edit_handle.php'),
    'viewforum'          => require_once('forum.php'),
    'viewthread'         => require_once('thread.php'),
    'viewtopic'          => require_once('thread.php'),
    'warn'               => require_once('warn.php'),
    default              => require_once('main.php'),
};
