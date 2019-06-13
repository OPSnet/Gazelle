<?php

namespace Gazelle;

class Contest {

	const CACHE_CONTEST_TYPE = 'contest_type';
	const CACHE_CONTEST = 'contest.%d';

	/** @var \DB_MYSQL */
	private $db;
	/** @var \CACHE */
	private $cache;

	private $type;

	public function __construct ($db, $cache) {
		$this->db = $db;
		$this->cache = $cache;
		$this->type = $this->cache->get_value(self::CACHE_CONTEST_TYPE);
		if ($this->type === false) {
			$this->db->query("SELECT ID, Name FROM contest_type ORDER BY ID");
			$this->type = $this->db->to_array('ID');
			$this->cache->cache_value(self::CACHE_CONTEST_TYPE, $this->type, 86400 * 7);
		}
	}

	public function get_type() {
		return $this->type;
	}

	public function get_list() {
		$this->db->query("
			SELECT c.ID, c.Name, c.DateBegin, c.DateEnd, t.ID as ContestType, (cbp.BonusPoolID IS NOT NULL) as BonusPool
			FROM contest c
			INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
			LEFT JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
			ORDER BY c.DateBegin DESC
		 ");
		 return $this->db->to_array();
	}

	public function get_contest($Id) {
		$key = sprintf(self::CACHE_CONTEST, $Id);
		$Contest = $this->cache->get_value($key);
		if ($Contest === false) {
			$this->db->prepared_query('
				SELECT c.ID, t.Name as ContestType, c.Name, c.Banner, c.WikiText, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd,
					coalesce(cbp.BonusPoolID, 0) as BonusPool,
					CASE WHEN now() BETWEEN c.DateBegin AND c.DateEnd THEN 1 ELSE 0 END as is_open
				FROM contest c
				INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
				LEFT JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
				WHERE c.ID=?' , $Id
			);
			if ($this->db->has_results()) {
				$Contest = $this->db->next_record(MYSQLI_ASSOC);
				$this->cache->cache_value($key, $Contest, 86400 * 3);
			}
		}
		return $Contest;
	}

	public function get_current_contest() {
		$Contest = $this->cache->get_value('contest_current');
		if ($Contest === false) {
			$this->db->query("
				SELECT c.ID, t.Name as ContestType, c.Name, c.Banner, c.WikiText, c.Display, c.MaxTracked, c.DateBegin, c.DateEnd,
					coalesce(cbp.BonusPoolID, 0) as BonusPool,
					CASE WHEN now() BETWEEN c.DateBegin AND c.DateEnd THEN 1 ELSE 0 END as is_open
				FROM contest c
				INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
				LEFT JOIN contest_has_bonus_pool cbp ON (cbp.ContestID = c.ID)
				WHERE c.DateEnd = (select max(DateEnd) from contest)
			");
			if ($this->db->has_results()) {
				$Contest = $this->db->next_record(MYSQLI_ASSOC);
				// Cache this for three days
				$this->cache->cache_value(sprintf(self::CACHE_CONTEST, $Contest['ID']), $Contest, 86400 * 3);
				$this->cache->cache_value('contest_current', $Contest, 86400 * 3);
			}
		}
		return $Contest;
	}

	public function get_prior_contests() {
		$Prior = $this->cache->get_value('contest_prior');
		if ($Prior === false) {
			$this->db->query("
				SELECT c.ID
				FROM contest c
				WHERE c.DateBegin < NOW()
				/* AND ... we may want to think about excluding certain past contests */
				ORDER BY c.DateBegin ASC
			");
			if ($this->db->has_results()) {
				$Prior = $this->db->to_array(false, MYSQLI_BOTH);
				$this->cache->cache_value('contest_prior', $Prior, 86400 * 3);
			}
		}
		return $Prior;
	}

	private function leaderboard_query($Contest) {
		/* only called from schedule, don't need to worry about caching this */
		switch ($Contest['ContestType']) {
			case 'upload_flac':
				/* how many 100% flacs uploaded? */
				$sql = "
					SELECT u.ID AS userid,
						count(*) AS nr,
						max(t.ID) as last_torrent
					FROM users_main u
					INNER JOIN torrents t ON (t.Userid = u.ID)
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
					GROUP By u.ID
				";
				$args = [
					$Contest['DateBegin'],
					$Contest['DateEnd']
				];
				break;

			case 'upload_flac_no_single':
				/* how many non-Single 100% flacs uploaded? */
				$sql = "
					SELECT u.ID AS userid,
						count(*) AS nr,
						max(t.ID) as last_torrent
					FROM users_main u
					INNER JOIN torrents t ON (t.Userid = u.ID)
					INNER JOIN torrents_group g ON (t.GroupID = g.ID)
					INNER JOIN release_type r ON (g.ReleaseType = r.ID)
					WHERE r.Name != 'Single'
						AND t.Format = 'FLAC'
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
					GROUP By u.ID
				";
				$args = [
					$Contest['DateBegin'],
					$Contest['DateEnd']
				];
				break;

			case 'request_fill':
				/* how many requests filled */
				$sql = "
					SELECT r.FillerID as userid,
						count(*) AS nr,
						max(if(r.TimeFilled = LAST.TimeFilled AND r.TimeAdded < ?, TorrentID, NULL)) as last_torrent
					FROM requests r
					INNER JOIN (
						SELECT r.FillerID,
							MAX(r.TimeFilled) as TimeFilled
						FROM requests r
						INNER JOIN users_main u ON (r.FillerID = u.ID)
						INNER JOIN torrents t ON (r.TorrentID = t.ID)
						WHERE r.TimeFilled BETWEEN ? AND ?
							AND r.FIllerId != r.UserID
							AND r.TimeAdded < ?
						GROUP BY r.FillerID
					) LAST USING (FillerID)
					WHERE r.TimeFilled BETWEEN ? AND ?
						AND r.FIllerId != r.UserID
						AND r.TimeAdded < ?
					GROUP BY r.FillerID
					";
				$args = [
					$Contest['DateBegin'],
					$Contest['DateBegin'],
					$Contest['DateEnd'],
					$Contest['DateBegin'],
					$Contest['DateBegin'],
					$Contest['DateEnd'],
					$Contest['DateBegin']
				];
				break;
			default:
				$sql = null;
				$args = [];
				break;
		}
		return [$sql, $args];
	}

	public function calculate_leaderboard() {
		$this->db->query("
			SELECT c.ID
			FROM contest c
			INNER JOIN contest_type t ON (t.ID = c.ContestTypeID)
			WHERE c.DateEnd > now() - INTERVAL 1 MONTH
			ORDER BY c.DateEnd DESC
		");
		$contest_id = [];
		while ($this->db->has_results()) {
			$c = $this->db->next_record();
			if (isset($c['ID'])) {
				$contest_id[] = $c['ID'];
			}
		}
		foreach ($contest_id as $id) {
			$Contest = $this->get_contest($id);
			list($subquery, $args) = $this->leaderboard_query($Contest);
			array_unshift($args, $id);
			if ($subquery) {
				$this->db->query("BEGIN");
				$this->db->prepared_query('DELETE FROM contest_leaderboard WHERE ContestID = ?', $id);
				$this->db->prepared_query_array("
					INSERT INTO contest_leaderboard
					SELECT ?, LADDER.userid,
						LADDER.nr,
						T.ID,
						TG.Name,
						group_concat(TA.ArtistID),
						group_concat(AG.Name order by AG.Name separator 0x1),
						T.Time
					FROM torrents_group TG
					LEFT JOIN torrents_artists TA ON (TA.GroupID = TG.ID)
					LEFT JOIN artists_group AG ON (AG.ArtistID = TA.ArtistID)
					INNER JOIN torrents T ON (T.GroupID = TG.ID)
					INNER JOIN (
						$subquery
					) LADDER on (LADDER.last_torrent = T.ID)
					GROUP BY
						LADDER.nr,
						T.ID,
						TG.Name,
						T.Time
				", $args);
				$this->db->query("COMMIT");
				$this->cache->delete_value('contest_leaderboard_' . $id);
				switch ($Contest['ContestType']) {
					case 'upload_flac':
						$this->db->prepared_query("
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
					case 'upload_flac_no_single':
						$this->db->prepared_query("
							SELECT count(*) AS nr
							FROM torrents t
							INNER JOIN torrents_group g ON (t.GroupID = g.ID)
							INNER JOIN release_type r ON (g.ReleaseType = r.ID)
							WHERE r.Name != 'Single'
								AND t.Format = 'FLAC'
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
						$this->db->prepared_query("
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
						$this->db->prepared_query("SELECT 0");
						break;
				}
				$this->cache->cache_value(
					"contest_leaderboard_total_{$Contest['ID']}",
					$this->db->has_results() ? $this->db->next_record()[0] : 0,
					3600 * 6
				);
				$this->get_leaderboard($id, false);
			}
		}
	}

	public function get_leaderboard($Id, $UseCache = true) {
		$Contest = $this->get_contest($Id);
		$Key = "contest_leaderboard_{$Contest['ID']}";
		$Leaderboard = $this->cache->get_value($Key);
		if (!$UseCache || $Leaderboard === false) {
			$this->db->query("
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
			$Leaderboard = $this->db->to_array(false, MYSQLI_BOTH);
			$this->cache->cache_value($Key, $Leaderboard, 60 * 20);
		}
		return $Leaderboard;
	}

	public function calculate_request_pairs() {
		$Contest = $this->get_current_contest();
		if ($Contest['ContestType'] != 'request_fill') {
			$Pairs = [];
		}
		else {
			$this->db->query("
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
			$Pairs = $this->db->to_array(false, MYSQLI_BOTH);
		}
		$this->cache->cache_value('contest_pairs_' . $Contest['ID'], $Pairs, 60 * 20);
	}

	public function get_request_pairs($UseCache = true) {
		$Contest = $this->get_current_contest();
		$Key = "contest_pairs_{$Contest['ID']}";
		if (($Pairs = $this->cache->get_value($Key)) === false) {
			$this->calculate_request_pairs();
			$Pairs = $this->cache->get_value($Key);
		}
		return $Pairs;
	}

	public function save($params) {
		if (isset($params['cid'])) {
			$this->db->prepared_query("
				UPDATE contest SET
					Name = ?, Display = ?, MaxTracked = ?, DateBegin = ?, DateEnd = ?,
					ContestTypeID = ?, Banner = ?, WikiText = ?
				WHERE ID = ?
				", $params['name'], $params['display'], $params['maxtrack'], $params['date_begin'], $params['date_end'],
					$params['type'], $params['banner'], $params['intro'],
					$params['cid']
			);
			$this->cache->delete_value('contest_current');
			$this->cache->delete_value("contest_{$params['cid']}");
			$contest_id = $params['cid'];
		}
		elseif (isset($params['new'])) {
			$this->db->prepared_query("
				INSERT INTO contest (Name, Display, MaxTracked, DateBegin, DateEnd, ContestTypeID, Banner, WikiText)
					VALUES (?, ?, ?, ?, ?, ?, ?, ?)
				",
					$params['name'], $params['display'], $params['maxtrack'], $params['date_begin'], $params['date_end'],
					$params['type'], $params['banner'], $params['intro']
			);
			$contest_id = $this->db->inserted_id();

			if (array_key_exists('pool', $params)) {
				$this->db->prepared_query("INSERT INTO bonus_pool (Name, SinceDate, UntilDate) VALUES (?, ?, ?)",
					$params['name'], $params['date_begin'], $params['date_end']
				);
				$pool_id = $this->db->inserted_id();
				$this->db->prepared_query("INSERT INTO contest_has_bonus_pool (ContestID, BonusPoolID) VALUES (?, ?)",
					$contest_id, $pool_id
				);
			}
		}
		return $contest_id;
	}
}
