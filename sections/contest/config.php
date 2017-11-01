<?php

function contest_config() {
    $contest = G::$Cache->get_value('contest_current');
    if ($contest === false) {
        G::$DB->query("
            SELECT ID, Name, Display, MaxTracked, DateBegin, DateEnd
            FROM contest
            WHERE now() BETWEEN DateBegin AND DateEnd
        ");
        if (G::$DB->has_results()) {
            $contest = G::$DB->next_record(MYSQLI_ASSOC);
            G::$Cache->cache_value('contest_current', $contest, 86400 * 3);
        }
    }
    return $contest;
}

function contest_leaderboard($id) {
    if (($CONTEST = contest_config()) === false) {
        return [];
    }
    $key = "contest_leaderboard_$id";
    $Leaderboard = G::$Cache->get_value($key);
    if ($Leaderboard === false) {
        $id = $CONTEST['ID'];
        $limit = $CONTEST['MaxTracked'];
        G::$DB->query("
            SELECT 
            	l.UserID,
                l.FlacCount,
                l.LastTorrentID,
                l.LastTorrentNAme,
                l.ArtistList,
                l.ArtistNames,
                l.LastUpload
            FROM contest_leaderboard l
            WHERE l.ContestID = $id
            ORDER BY l.FlacCount DESC, l.LastUpload ASC, l.UserID ASC
            LIMIT $limit
        ");
        $Leaderboard = G::$DB->to_array(false, MYSQLI_NUM);
        G::$Cache->cache_value($key, $Leaderboard, 60 * 20);
    }
    return $Leaderboard;
}
