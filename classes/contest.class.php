<?php

class Contest {
	private static $contest_type;

	public static function get_contest($Id) {
		$Contest = G::$Cache->get_value("contest_{$Id}");
		if ($Contest === false) {
			G::$DB->query("
				SELECT c.ID, t.Name as ContestType, c.Name, c.Banner, c.WikiText, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd,
					CASE WHEN now() BETWEEN c.DateBegin AND c.DateEnd THEN 1 ELSE 0 END as is_open
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
				SELECT c.ID, t.Name as ContestType, c.Name, c.Banner, c.WikiText, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd,
					CASE WHEN now() BETWEEN c.DateBegin AND c.DateEnd THEN 1 ELSE 0 END as is_open
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

	public static function get_prior_contests() {
		$Prior = G::$Cache->get_value('contest_prior');
		if ($Prior === false) {
			G::$DB->query("
				SELECT c.ID
				FROM contest c
				WHERE c.DateBegin < NOW()
				/* AND ... we may want to think about excluding certain past contests */
				ORDER BY c.DateBegin ASC
			");
			if (G::$DB->has_results()) {
				$Prior = G::$DB->to_array(false, MYSQLI_BOTH);
				G::$Cache->cache_value('contest_prior', $Prior, 86400 * 3);
			}
		}
		return $Prior;
	}

	private static function leaderboard_query($Contest) {
		/* only called from schedule, don't need to worry about caching this */
		switch ($Contest['ContestType']) {
			case 'upload_flac':
			case 'upload_flac_strict_rank':
				/* how many 100% flacs uploaded? */
				$sql = "
					SELECT u.ID AS userid,
						count(*) AS nr,
						max(t.ID) as last_torrent
					FROM users_main u
					INNER JOIN torrents t ON (t.Userid = u.ID)
					WHERE t.Format = 'FLAC'
						AND t.Time BETWEEN '{$Contest['DateBegin']}' AND '{$Contest['DateEnd']}'
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
						max(if(r.TimeFilled = LAST.TimeFilled AND r.TimeAdded < '{$Contest['DateBegin']}', TorrentID, NULL)) as last_torrent
					FROM requests r
					INNER JOIN (
						SELECT r.FillerID,
							MAX(r.TimeFilled) as TimeFilled
						FROM requests r
						INNER JOIN users_main u ON (r.FillerID = u.ID)
						WHERE r.TimeFilled BETWEEN '{$Contest['DateBegin']}' AND '{$Contest['DateEnd']}'
							AND r.FIllerId != r.UserID
							AND r.TimeAdded < '{$Contest['DateBegin']}'
						GROUP BY r.FillerID
					) LAST USING (FillerID)
					WHERE r.TimeFilled BETWEEN '{$Contest['DateBegin']}' AND '{$Contest['DateEnd']}'
						AND r.FIllerId != r.UserID
						AND r.TimeAdded < '{$Contest['DateBegin']}'
					GROUP BY r.FillerID
					";
				break;
			default:
				$sql = null;
				break;
		}
		return $sql;
	}

	public static function calculate_leaderboard() {
		G::$DB->query("
			SELECT c.ID
			FROM contest c
			INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
			WHERE c.DateEnd > now() - INTERVAL 1 MONTH
			ORDER BY c.DateEnd DESC
		");
		$contest_id = [];
		while (G::$DB->has_results()) {
			$c = G::$DB->next_record();
			if (isset($c['ID'])) {
				$contest_id[] = $c['ID'];
			}
		}
		foreach ($contest_id as $id) {
			$Contest = Contest::get_contest($id);
			$subquery = self::leaderboard_query($Contest);
			if ($subquery) {
				$begin = time();
				G::$DB->query("BEGIN");
				G::$DB->query("DELETE FROM contest_leaderboard where ContestID = $id");
				G::$DB->query("
					INSERT INTO contest_leaderboard
					SELECT $id, LADDER.userid,
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
				G::$Cache->delete_value('contest_leaderboard_' . $id);
				switch ($Contest['ContestType']) {
					case 'upload_flac':
					case 'upload_flac_strict_rank':
						G::$DB->prepared_query("
							SELECT count(*) AS nr
							FROM torrents t
							WHERE t.Format = 'FLAC'
								AND t.Time BETWEEN ? AND ?
								AND (
									t.Media IN ('Vinyl', 'WEB')
									OR (t.Media = 'CD'
										AND t.HasLog = '1'
										AND t.HasCue = '1'
										AND t.LogScore = 100
										AND t.LogChecksum = '1'
									)
								)
							", $Contest['DateBegin'], $Contest['DateEnd']
						);
						break;
					case 'request_fill':
						G::$DB->prepared_query("
							SELECT
								count(*) AS nr
							FROM requests r
							INNER JOIN users_main u ON (r.FillerID = u.ID)
							WHERE r.TimeFilled BETWEEN ? AND ?
								AND r.FIllerId != r.UserID
								AND r.TimeAdded < ?
							", $Contest['DateBegin'], $Contest['DateEnd'], $Contest['DateBegin']
						);
						break;
					default:
						G::$DB->prepared_query("SELECT 0");
						break;
				}
				G::$Cache->cache_value(
					"contest_leaderboard_total_{$Contest['ID']}",
					G::$DB->has_results() ? G::$DB->next_record()[0] : 0,
					3600 * 6
				);
				self::get_leaderboard($id, false);
			}
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

	public static function save($params) {
		G::$DB->query("
			UPDATE contest SET
				Name		= '".db_string($params['name'])."',
				Display		= {$params['display']},
				MaxTracked	= {$params['maxtrack']},
				DateBegin	= '".db_string($params['date_begin'])."',
				DateEnd		= '".db_string($params['date_end'])."',
				ContestTypeID	= {$params['type']},
				Banner		= '".db_string($params['banner'])."',
				WikiText	= '".db_string($params['intro'])."'
			WHERE ID = {$params['cid']}
		");
		G::$Cache->delete_value('contest_current');
		G::$Cache->delete_value("contest_{$params['cid']}");
	}
}
