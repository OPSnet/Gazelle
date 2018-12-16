<?php

$DB->prepared_query("
	INSERT INTO users_summary (UserID, Groups)
		SELECT UserID, COUNT(DISTINCT GroupID)
		FROM torrents t
		INNER JOIN users_main u ON u.ID = t.UserID
		GROUP BY UserID
	ON DUPLICATE KEY UPDATE Groups = VALUES(Groups)");

$DB->prepared_query("
INSERT INTO users_summary (UserID, PerfectFlacs)
	SELECT t.UserID, COUNT(t.ID)
	FROM torrents t
	INNER JOIN users_main u ON u.ID = t.UserID
	WHERE ( t.Format = 'FLAC'
		AND (
			(t.LogScore = 100 AND t.Media = 'CD')
            OR
            t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'BD', 'DAT')
		)
	)
	GROUP BY t.UserID
ON DUPLICATE KEY UPDATE PerfectFlacs = VALUES(PerfectFlacs)");

