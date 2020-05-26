<?php

/* If ever can_leech and RatioWatchEnds get out of synch, no task will catch it */

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');
require_once(__DIR__.'/../classes/util.php');

$Debug = new DEBUG;
$Debug->handle_errors();

ini_set('max_execution_time', -1);

$DB = new DB_MYSQL;
$Cache = new CACHE;

$DB->prepared_query('
    SELECT um.ID, um.torrent_pass
    FROM users_main um
    INNER JOIN users_info ui on (ui.UserID = um.ID)
    WHERE um.can_leech = 0
        AND ui.RatioWatchEnds IS NULL
');
$users = $DB->to_array('ID', MYSQLI_ASSOC, false);

foreach ($users as $u) {
    \Tracker::update_tracker('update_user', ['passkey' => $u['torrent_pass'], 'can_leech' => '1']);
    $DB->prepared_query('
        UPDATE users_main SET
            can_leech = 1
        WHERE ID = ?
        ', $u['ID']
    );
    $Cache->delete_value("user_info_heavy_" . $u['ID']);
    echo $u['ID'] . "\n";
}
