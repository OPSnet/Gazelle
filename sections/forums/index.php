<?php

enforce_login();

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$Forums = Forums::get_forums();
$ForumCats = Forums::get_forum_categories();

G::$Router->addGet('', SERVER_ROOT.'/sections/forums/main.php');

G::$Router->addPost('reply', SERVER_ROOT.'/sections/forums/take_reply.php');
G::$Router->addPost('new', SERVER_ROOT.'/sections/forums/take_new_thread.php');
G::$Router->addPost('mod_thread', SERVER_ROOT.'/sections/forums/mod_thread.php');
G::$Router->addPost('poll_mod', SERVER_ROOT.'/sections/forums/poll_mod.php');
G::$Router->addPost('add_poll_option', SERVER_ROOT.'/sections/forums/add_poll_option.php');
G::$Router->addPost('warn', SERVER_ROOT.'/sections/forums/warn.php');
G::$Router->addPost('take_warn', SERVER_ROOT.'/sections/forums/take_warn.php');
G::$Router->addPost('take_topic_notes', SERVER_ROOT.'/sections/forums/take_topic_notes.php');
G::$Router->addPost('takeedit', SERVER_ROOT.'/sections/forums/takeedit.php');

G::$Router->addGet('viewforum', SERVER_ROOT.'/sections/forums/forum.php');
G::$Router->addGet('viewthread', SERVER_ROOT.'/sections/forums/thread.php');
G::$Router->addGet('viewtopic', SERVER_ROOT.'/sections/forums/thread.php');
G::$Router->addGet('ajax_get_edit', SERVER_ROOT.'/sections/forums/ajax_get_edit.php');
G::$Router->addGet('new', SERVER_ROOT.'/sections/forums/newthread.php');
G::$Router->addGet('takeedit', SERVER_ROOT.'/sections/forums/takeedit.php');
G::$Router->addGet('get_post', SERVER_ROOT.'/sections/forums/get_post.php');
G::$Router->addGet('delete', SERVER_ROOT.'/sections/forums/delete.php');
G::$Router->addGet('catchup', SERVER_ROOT.'/sections/forums/catchup.php');
G::$Router->addGet('search', SERVER_ROOT.'/sections/forums/search.php');
G::$Router->addGet('change_vote', SERVER_ROOT.'/sections/forums/change_vote.php');
G::$Router->addGet('delete_poll_option', SERVER_ROOT.'/sections/forums/delete_poll_option.php');
G::$Router->addGet('sticky_post', SERVER_ROOT.'/sections/forums/sticky_post.php');
G::$Router->addGet('edit_rules', SERVER_ROOT.'/sections/forums/edit_rules.php');
//G::$Router->addGet('thread_subscribe', '');
G::$Router->addGet('warn', SERVER_ROOT.'/sections/forums/warn.php');
