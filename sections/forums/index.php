<?php

if ($Viewer->disableForums()) {
    error(403);
}

$Router->addGet('', __DIR__ . '/main.php');

$Router->addPost('add_poll_option',  __DIR__ . '/add_poll_option.php');
$Router->addPost('mod_thread',       __DIR__ . '/mod_thread.php');
$Router->addPost('new',              __DIR__ . '/take_new_thread.php');
$Router->addPost('poll_mod',         __DIR__ . '/poll_mod.php');
$Router->addPost('reply',            __DIR__ . '/take_reply.php');
$Router->addPost('take_topic_notes', __DIR__ . '/take_topic_notes.php');
$Router->addPost('take_warn',        __DIR__ . '/take_warn.php');
$Router->addPost('takeedit',         __DIR__ . '/takeedit.php');
$Router->addPost('warn',             __DIR__ . '/warn.php');

$Router->addGet('ajax_get_edit',      __DIR__ . '/ajax_get_edit.php');
$Router->addGet('catchup',            __DIR__ . '/catchup.php');
$Router->addGet('change_vote',        __DIR__ . '/change_vote.php');
$Router->addGet('delete',             __DIR__ . '/delete.php');
$Router->addGet('delete_poll_option', __DIR__ . '/delete_poll_option.php');
$Router->addGet('get_post',           __DIR__ . '/get_post.php');
$Router->addGet('new',                __DIR__ . '/newthread.php');
$Router->addGet('search',             __DIR__ . '/search.php');
$Router->addGet('sticky_post',        __DIR__ . '/sticky_post.php');
$Router->addGet('takeedit',           __DIR__ . '/takeedit.php');
$Router->addGet('viewforum',          __DIR__ . '/forum.php');
$Router->addGet('viewthread',         __DIR__ . '/thread.php');
$Router->addGet('viewtopic',          __DIR__ . '/thread.php');
$Router->addGet('warn',               __DIR__ . '/warn.php');
