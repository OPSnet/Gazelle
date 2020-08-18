<?php

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');
require_once(__DIR__.'/../classes/util.php');

$Debug = new DEBUG;
$Debug->handle_errors();

$DB = new DB_MYSQL;
$Cache = new CACHE;

G::$Cache = $Cache;
G::$DB = $DB;

$DB->prepared_query("
    DELETE FROM invite_tree
");
$invite = $DB->prepared_query('
	SELECT UserID, Inviter
    FROM users_info
    WHERE Inviter IS NOT NULL
    ORDER BY UserID
');
$inv = [];
while ([$invitee, $inviter] = $DB->next_record()) {
	$save = $DB->get_query_id();
    if (!isset($inv[$inviter])) {
        $inv[$inviter] = new Gazelle\InviteTree($inviter);
    }
    $inv[$inviter]->add($invitee);
	$DB->set_query_id($save);
}
