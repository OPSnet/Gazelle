<?php

enforce_login();

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$Forums = Forums::get_forums();
$ForumCats = Forums::get_forum_categories();

G::$Router->addGet('', __DIR__ . '/main.php');

G::$Router->addPost('add_poll_option',  __DIR__ . '/add_poll_option.php');
G::$Router->addPost('mod_thread',       __DIR__ . '/mod_thread.php');
G::$Router->addPost('new',              __DIR__ . '/take_new_thread.php');
G::$Router->addPost('poll_mod',         __DIR__ . '/poll_mod.php');
G::$Router->addPost('reply',            __DIR__ . '/take_reply.php');
G::$Router->addPost('take_topic_notes', __DIR__ . '/take_topic_notes.php');
G::$Router->addPost('take_warn',        __DIR__ . '/take_warn.php');
G::$Router->addPost('takeedit',         __DIR__ . '/takeedit.php');
G::$Router->addPost('warn',             __DIR__ . '/warn.php');

G::$Router->addGet('ajax_get_edit',      __DIR__ . '/ajax_get_edit.php');
G::$Router->addGet('catchup',            __DIR__ . '/catchup.php');
G::$Router->addGet('change_vote',        __DIR__ . '/change_vote.php');
G::$Router->addGet('delete',             __DIR__ . '/delete.php');
G::$Router->addGet('delete_poll_option', __DIR__ . '/delete_poll_option.php');
G::$Router->addGet('get_post',           __DIR__ . '/get_post.php');
G::$Router->addGet('new',                __DIR__ . '/newthread.php');
G::$Router->addGet('search',             __DIR__ . '/search.php');
G::$Router->addGet('sticky_post',        __DIR__ . '/sticky_post.php');
G::$Router->addGet('takeedit',           __DIR__ . '/takeedit.php');
G::$Router->addGet('viewforum',          __DIR__ . '/forum.php');
G::$Router->addGet('viewthread',         __DIR__ . '/thread.php');
G::$Router->addGet('viewtopic',          __DIR__ . '/thread.php');
G::$Router->addGet('warn',               __DIR__ . '/warn.php');
