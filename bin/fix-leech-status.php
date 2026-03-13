<?php

/* If ever can_leech and RatioWatchEnds get out of synch, no task will catch it */

namespace Gazelle;

require __DIR__ . '/../lib/bootstrap.php';

ini_set('max_execution_time', -1);

$tracker = new Tracker();
$userMan = new Manager\User();

$db = DB::DB();
$db->prepared_query('
    SELECT um.ID
    FROM users_main um
    INNER JOIN users_info ui on (ui.UserID = um.ID)
    WHERE um.can_leech = 0
        AND ui.RatioWatchEnds IS NULL
');

foreach ($db->collect(0) as $userId) {
    $user = $userMan->findById($userId);
    $user->setField('can_leech', 1)->modify();
    $tracker->refreshUser($user);
    echo "$userId\t{$user->username()}\n";
}
