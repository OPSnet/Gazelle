<?php

class Contest {
	private static $contest_type;

	public static function get_contest($Id) {
		$Contest = G::$Cache->get_value("contest_{$Id}");
		if ($Contest === false) {
			G::$DB->query("
				SELECT c.ID, t.Name as ContestType, c.Name, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd
				FROM contest c
				INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
				WHERE c.ID={$Id}
			");
			if (G::$DB->has_results()) {
				$Contest = G::$DB->next_record(MYSQLI_ASSOC);
				G::$Cache->cache_value("contest_{$Id}", $Contest, 86400 * 3);
			}
		}
		return $Contest;
	}

	public static function get_current_contest() {
		$Contest = G::$Cache->get_value('contest_current');
		if ($Contest === false) {
			G::$DB->query("
				SELECT c.ID, t.Name as ContestType, c.Name, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd
				FROM contest c
				INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
				WHERE c.DateEnd = (select max(DateEnd) from contest)
			");
			if (G::$DB->has_results()) {
				$Contest = G::$DB->next_record(MYSQLI_ASSOC);
				// Cache this for three days
				G::$Cache->cache_value("contest_{$Contest['ID']}", $Contest, 86400 * 3);
				G::$Cache->cache_value('contest_current', $Contest, 86400 * 3);
			}
		}
		return $Contest;
	}

	private static function leaderboard_query() {
		/* return the query that counts whatever it is that the ladder is measuring */
		G::$DB->query("
			SELECT c.ID, t.Name, c.DateBegin, c.DateEnd
			FROM contest c
			INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
			ORDER BY c.DateEnd DESC
			LIMIT 1
		");
		/* called from schedule, don't need to worry about caching this */
		list($contest_id, $contest_type, $dt_begin, $dt_end) = G::$DB->next_record();
		switch ($contest_type) {
			case 'upload_flac':
				/* how many 100% flacs uploaded? */
				$sql = "
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
				";
				break;
			case 'request_fill':
				/* how many requests filled */
				$sql = "
					SELECT r.FillerID as userid,
						count(*) AS nr,
						max(if(r.TimeFilled = LAST.TimeFilled, TorrentID, NULL)) as last_torrent
					FROM requests r
					INNER JOIN (
						SELECT r.FillerID,
							MAX(r.TimeFilled) as TimeFilled
						FROM requests r
						INNER JOIN users_main u ON (r.FillerID = u.ID)
						WHERE r.TimeFilled BETWEEN '$dt_begin' AND '$dt_end'
							AND r.FIllerId != r.UserID
							AND r.TimeAdded < '$dt_begin'
						GROUP BY r.FillerID
					) LAST USING (FillerID)
					GROUP BY r.FillerID
				";
				break;
			default:
				$sql = null;
				break;
		}
		return [$contest_id, $sql];
	}

	public static function calculate_leaderboard() {
		list($Contest_id, $subquery) = self::leaderboard_query();
		if ($subquery) {
			$begin = time();
			G::$DB->query("BEGIN");
			G::$DB->query("DELETE FROM contest_leaderboard where ContestID = $Contest_id");
			G::$DB->query("
				INSERT INTO contest_leaderboard
				SELECT $Contest_id, LADDER.userid,
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
					$subquery
				) LADDER on (LADDER.last_torrent = T.ID)
				GROUP BY
					LADDER.nr,
					T.ID,
					TG.Name,
					T.Time
			");
			G::$DB->query("COMMIT");
			G::$Cache->delete_value('contest_leaderboard_' . $Contest_id);
			self::get_leaderboard($Contest_id, false);
		}
	}

	public static function get_leaderboard($Id, $UseCache = true) {
		$Contest = self::get_contest($Id);
		$Key = "contest_leaderboard_{$Contest['ID']}";
		$Leaderboard = G::$Cache->get_value($Key);
		if (!$UseCache || $Leaderboard === false) {
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
			WHERE l.ContestID = {$Contest['ID']}
			ORDER BY l.FlacCount DESC, l.LastUpload ASC, l.UserID ASC
			LIMIT {$Contest['MaxTracked']}");
			$Leaderboard = G::$DB->to_array(false, MYSQLI_BOTH);
			G::$Cache->cache_value($Key, $Leaderboard, 60 * 20);
		}
		return $Leaderboard;
	}

	public static function calculate_request_pairs() {
		$Contest = self::get_current_contest();
		if ($Contest['ContestType'] != 'request_fill') {
			$Pairs = [];
		}
		else {
			G::$DB->query("
				SELECT r.FillerID, r.UserID, count(*) as nr
				FROM requests r
				WHERE r.TimeFilled BETWEEN '{$Contest['DateBegin']}' AND '{$Contest['DateEnd']}'
				GROUP BY
					r.FillerID, r.UserId
				HAVING count(*) > 1
				ORDER BY
					count(*) DESC, r.FillerID ASC
				LIMIT 100
			");
			$Pairs = G::$DB->to_array(false, MYSQLI_BOTH);
		}
		G::$Cache->cache_value('contest_pairs_' . $Contest['ID'], $Pairs, 60 * 20);
	}

	public static function get_request_pairs($UseCache = true) {
		$Contest = self::get_current_contest();
		$Key = "contest_pairs_{$Contest['ID']}";
		if (($Pairs = G::$Cache->get_value($Key)) === false) {
			self::calculate_request_pairs();
			$Pairs = G::$Cache->get_value($Key);
		}
		return $Pairs;
	}

	public static function init_admin() {
		/* need to call this from the admin page to preload the contest dropdown,
		 * since Gazelle doesn't allow multiple open db statements.
		 */
		self::$contest_type = [];
		G::$DB->query("SELECT ID, Name FROM contest_type ORDER BY ID");
		if (G::$DB->has_results()) {
			while ($Row = G::$DB->next_record()) {
				self::$contest_type[$Row[0]] = $Row[1];
			}
		}
	}

	public static function contest_type() {
		return self::$contest_type;
	}
}
