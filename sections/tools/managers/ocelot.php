<?php

if (!(
       ($_SERVER['REMOTE_ADDR'] ?? '') === TRACKER_HOST
    && ($_GET['key']            ?? '') === TRACKER_SECRET
    && ($_GET['type']           ?? '') === 'expiretoken'
    && isset($_GET['tokens'])
)) {
    error(403);
}

(new Gazelle\Tracker)->expireFreeleechTokens($_GET['tokens']);
