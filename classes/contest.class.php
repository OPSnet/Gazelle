<?php

class Contest {
	public static function get_contest($Id) {
		$Contest = G::$Cache->get_value("contest_{$Id}");
		if ($Contest === false) {
			G::$DB->query("SELECT ID, Name, Display, MaxTracked, DateBegin, DateEnd FROM contest WHERE ID={$Id}");
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
			SELECT ID, Name, Display, MaxTracked, DateBegin, DateEnd
			FROM contest
			WHERE now() BETWEEN DateBegin AND DateEnd");

			if (G::$DB->has_results()) {
				$Contest = G::$DB->next_record(MYSQLI_ASSOC);
				// Cache this for three days
				G::$Cache->cache_value("contest_{$Contest['ID']}", $Contest, 86400 * 3);
				G::$Cache->cache_value('contest_current', $Contest, 86400 * 3);
			}
		}
		return $Contest;
	}

	public static function get_leaderboard($Id, $UseCache = true) {
		$Contest = Contest::get_contest($Id);
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
}