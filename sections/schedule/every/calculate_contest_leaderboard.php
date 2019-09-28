<?php

$ContestMgr = new \Gazelle\Contest(G::$DB, G::$Cache);

$ContestMgr->calculate_leaderboard();
$ContestMgr->calculate_request_pairs();

$total = $ContestMgr->schedule_payout();
if ($total) {
    echo "$total messages sent\n";
}
