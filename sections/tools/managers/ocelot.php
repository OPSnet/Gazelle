<?php

if (
       ($_SERVER['REMOTE_ADDR'] ?? '') !== TRACKER_HOST
    || ($_REQUEST['key'] ?? '') !== TRACKER_SECRET
    || ($_REQUEST['type'] ?? '') !== 'expiretoken'
) {
    error(403);
}

$db = Gazelle\DB::DB();
if (isset($_GET['tokens'])) {
    $Tokens = explode(',', $_GET['tokens']);
    if (empty($Tokens)) {
        error(0);
    }
    $cond = [];
    $args = [];
    $ck = [];
    foreach ($Tokens as $Token) {
        [$UserID, $TorrentID] = array_map('intval', explode(':', $Token));
        if (!$UserID || !$TorrentID) {
            continue;
        }
        $cond[] = "(UserID = ? AND TorrentID = ?)";
        $args = array_merge($args, [$UserID, $TorrentID]);
        $ck[] = "users_tokens_$UserID";
    }
    if ($cond) {
        $db->prepared_query("
            UPDATE users_freeleeches SET
                Expired = TRUE
            WHERE "
            . implode(" OR ", $cond), ...$args
        );
        $Cache->delete_multi($ck);
    }
} else {
    $TorrentID = (int)$_REQUEST['torrentid'];
    $UserID = (int)$_REQUEST['userid'];
    if (!$TorrentID || !$UserID) {
        error(403);
    }
    $db->prepared_query("
        UPDATE users_freeleeches SET
            Expired = TRUE
        WHERE UserID = ? AND TorrentID = ?
        ", $UserID, $TorrentID
    );
    $Cache->delete_value("users_tokens_$UserID");
}
