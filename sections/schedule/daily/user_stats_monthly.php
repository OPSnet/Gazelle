<?php

$DB->prepared_query("
INSERT INTO users_stats_monthly (UserID, Uploaded, Downloaded, BonusPoints, Torrents, PerfectFLACs)
SELECT um.ID, uls.Uploaded, uls.Downloaded, um.BonusPoints, COUNT(t.ID) AS Torrents, COALESCE(p.Perfects, 0) AS PerfectFLACs
FROM users_main um
INNER JOIN users_leech_stats uls ON uls.UserID = um.ID
LEFT JOIN torrents t ON t.UserID = um.ID
LEFT JOIN
(
    SELECT UserID, COUNT(ID) AS Perfects
    FROM torrents
    WHERE( Format = 'FLAC'
        AND (
            Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT')
            OR
            (LogScore = 100 AND Media = 'CD')))
    GROUP BY UserID
) p ON p.UserID = um.ID
GROUP BY um.ID;");

$DB->prepared_query("
DELETE FROM users_stats_monthly
WHERE Time < NOW() - INTERVAL 32 DAY");

