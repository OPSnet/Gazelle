<?php

define("CONTEST_ID", 0);
define("CONTEST_NAME", 1);
define("CONTEST_DISPLAYED", 2);
define("CONTEST_MAXTRACKED", 3);
define("CONTEST_DATE_BEGIN", 4);
define("CONTEST_DATE_END", 5);

function contest_config() {
    $contest = G::$Cache->get_value('contest_current');
    if ($contest === false) {
        G::$DB->query("
            SELECT ID, Name, Display, MaxTracked, DTBegin, DTEnd
            FROM contest
            WHERE now() BETWEEN DTBegin AND DTEnd
        ");
        if (G::$DB->has_results()) {
            $contest = G::$DB->next_record(MYSQLI_NUM);
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
        $id = $CONTEST[CONTEST_ID];
        $limit = $CONTEST[CONTEST_MAXTRACKED];
        G::$DB->query("
            SELECT l.UserID,
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
