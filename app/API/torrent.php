<?php

namespace Gazelle\API;

class Torrent extends AbstractAPI {
	public function run() {
		switch ($_GET['req']) {
			case 'group':
				return $this->getGroup();
				break;
			default:
			case 'torrent':
				return $this->getTorrent();
				break;
		}
	}

	private function getTorrent() {
		if (!isset($_GET['torrent_id'])) {
			json_error('Missing torrent id');
		}

		$this->db->prepared_query("
			SELECT
				tg.ID,
				tg.Name,
				tg.Year,
				t.Media,
				t.Format
				t.HasLog,
				t.HasLogDB,
				t.LogScore,
				t.Snatched,
				t.Seeders,
				t.Leechers
			FROM
				torrents AS t
				INNER JOIN torrents_groups AS tg ON tg.ID = t.GroupID
			WHERE
				t.ID = ?", $_GET['torrent_id']);
		if (!$this->db->has_results()) {
			json_error('Torrent not found');
		}
		$torrent = $this->db->next_record(MYSQLI_ASSOC, false);
		$artists = \Artists::get_artist($torrent['ID']);
		$torrent['Artists'] = $artists;
		$torrent['DisplayArtists'] = \Artists::display_artists($artists,
			false, false, false);
		return $torrent;
	}

	private function getGroup() {
		if (!isset($_GET['group_id'])) {
			json_error('Missing group id');
		}

		$this->db->prepared_query("
			SELECT
				g.ID,
				g.Name,
				g.Year
			FROM
				torrents_group
			WHERE
				ID = ?", $_GET['group_id']);
		if (!$this->db->has_results()) {
			json_error('Group not found');
		}
		$group = $this->db->next_record(MYSQLI_ASSOC, false);
		$artists = \Artists::get_artist($group['ID']);
		$group['Artists'] = $artists;
		$group['ArtistsFormatted'] = \Artists::display_artists($artists,
			false, false, false);
		return $group;
	}
}
