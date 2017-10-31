<?php

include(SERVER_ROOT.'/sections/contest/config.php');

if (($CONTEST = contest_config()) !== false) {
    $begin = time();
    $contest_id = $CONTEST['ID'];
    $dt_begin = $CONTEST['Date_Begin'];
    $dt_end = $CONTEST['Date_End'];

    $DB->query("DELETE FROM contest_leaderboard where ContestID = $contest_id");
    $DB->query("
INSERT INTO contest_leaderboard
SELECT $contest_id, LADDER.userid,
    LADDER.nr,
    T.ID,
    TG.Name,
    group_concat(TA.ArtistID),
    group_concat(AG.Name order by AG.Name separator 0x1),
    T.Time
FROM torrents_artists TA
INNER JOIN torrents_group TG ON (TG.ID = TA.GroupID)
INNER JOIN artists_group AG ON (AG.ArtistID = TA.ArtistID)
INNER JOIN torrents T ON (T.GroupID = TG.ID)
INNER JOIN (
    SELECT u.ID AS userid,
        count(*) AS nr,
        max(t.ID) as last_torrent
    FROM users_main u
    INNER JOIN torrents t ON (t.Userid = u.ID)
    WHERE t.Format = 'FLAC'
        AND t.Time BETWEEN '$dt_begin' AND '$dt_end'
        AND (
            t.Media IN ('Vinyl', 'WEB')
            OR (t.Media = 'CD'
                AND t.HasLog = '1'
                AND t.HasCue = '1'
                AND t.LogScore = 100
                AND t.LogChecksum = '1'
            )
        )
    GROUP By u.ID
) LADDER on (LADDER.last_torrent = T.ID)
GROUP BY
    LADDER.nr,
    T.ID,
    TG.Name,
    T.Time
    ");
    contest_leaderboard($contest_id);
    printf("Contest $contest_id recalculated in %d seconds\n", time() - $begin);
}
