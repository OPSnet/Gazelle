<?php

namespace Gazelle\API;

class User extends AbstractAPI {
	private $id = null;
	private $username = null;
	private $clear_tokens = false;

	public function run() {
		if (isset($_GET['user_id'])) {
			$this->id = intval($_GET['user_id']);
		}
		else if (isset($_GET['username'])) {
			$this->username = $_GET['username'];
		}
		else {
			json_error("Need to supply either user_id or username");
		}

		if (isset($_GET['clear_tokens'])) {
			$this->clear_tokens = true;
		}

		switch ($_GET['req']) {
			case 'enable':
				return $this->enableUser();
				break;
			case 'disable':
				return $this->disableUser();
				break;
			default:
			case 'stats':
				return $this->getUser();
				break;
		}
	}

	private function getUser() {
		$where = ($this->id !== null) ? "um.ID = ?" : "um.Username = ?";
		$this->db->prepared_query("
			SELECT
				um.ID,
				um.Username,
				um.Enabled,
				um.IRCKey,
				um.Uploaded,
				um.Downloaded,
				um.PermissionID AS Class,
				um.Paranoia,
				um.BonusPoints,
				ui.DisableIRC,
				p.Name as ClassName,
				p.Level,
				GROUP_CONCAT(ul.PermissionID SEPARATOR ',') AS SecondaryClasses
			FROM
				users_main AS um
				INNER JOIN users_info AS ui ON ui.UserID = um.ID
				INNER JOIN permissions AS p ON p.ID = um.PermissionID
				LEFT JOIN users_levels AS ul ON ul.UserID = um.ID
			WHERE
				{$where}", ($this->id !== null) ? $this->id : $this->username);

		$user = $this->db->next_record(MYSQLI_ASSOC, array('IRCKey', 'Paranoia'));
		if (!empty($user['Username'])) {
			$user['SecondaryClasses'] = array_map("intval", explode(",", $user['SecondaryClasses']));
			foreach (array('ID', 'Uploaded', 'Downloaded', 'Class', 'Level') as $key) {
				$user[$key] = intval($user[$key]);
			}
			$user['Paranoia'] = unserialize_array($user['Paranoia']);

			$user['Ratio'] = \Format::get_ratio($user['Uploaded'], $user['Downloaded']);
			$user['DisplayStats'] = array(
				'Downloaded' => \Format::get_size($user['Downloaded']),
				'Uploaded' => \Format::get_size($user['Uploaded']),
				'Ratio' => $user['Ratio']
			);
			foreach (array('Downloaded', 'Uploaded', 'Ratio') as $key) {
				if (in_array(strtolower($key), $user['Paranoia'])) {
					$user['DisplayStats'][$key] = "Hidden";
				}
			}
			$user['UserPage'] = site_url() . "user.php?id={$user['ID']}";
		}
		return $user;
	}

	private function disableUser() {
		if ($this->id === null) {
			$this->db->prepared_query("SELECT ID FROM users_main WHERE Username = ?",
				$this->username);
			if ($this->db->has_results()) {
				$user = $this->db->next_record(MYSQLI_ASSOC, false);
				$this->id = $user['ID'];
			} else {
				json_error("No user found with username {$this->username}");
			}
		}

		\Tools::disable_users($this->id, 'Disabled via API', 1);
		return array('disabled' => true, 'user_id' => $this->id, 'username' => $this->username);
	}

	private function enableUser() {
		$where = ($this->id !== null) ? "um.ID = ?" : "um.Username = ?";
		$this->db->prepared_query("
			SELECT
				um.ID,
				um.Username,
				um.IP,
				um.Enabled,
				um.Uploaded,
				um.Downloaded,
				um.Visible,
				ui.AdminComment,
				um.torrent_pass,
				um.RequiredRatio,
				ui.RatioWatchEnds
			FROM 
				users_main AS um
				INNER JOIN users_info AS ui ON ui.UserID = um.ID
			WHERE
				{$where}", ($this->id !== null) ? $this->id : $this->username);

		// TODO: merge this and the version in takemoderate.php
		$UpdateSet = array();
		$Cur = $this->db->next_record(MYSQLI_ASSOC, false);
		$Comment = 'Enabled via API';

		if ($this->clear_tokens) {
			$UpdateSet[] = "um.Invites = '0'";
			$UpdateSet[] = "um.FLTokens = '0'";
			$Comment = 'Tokens and invites reset, enabled via API';
		}

		$this->cache->increment('stats_user_count');
		$VisibleTrIp = $Cur['Visible'] && $Cur['IP'] != '127.0.0.1' ? '1' : '0';
		\Tracker::update_tracker('add_user', array('id' => $this->id,
			'passkey' => $Cur['torrent_pass'], 'visible' => $VisibleTrIp));
		if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded'] / $Cur['Downloaded'] >=
			$Cur['RequiredRatio'])) {
			$UpdateSet[] = "ui.RatioWatchEnds = '0000-00-00 00:00:00'";
			$UpdateSet[] = "um.can_leech = '1'";
			$UpdateSet[] = "ui.RatioWatchDownload = '0'";
		} else {
			if ($Cur['RatioWatchEnds'] != '0000-00-00 00:00:00') {
				$UpdateSet[] = "ui.RatioWatchEnds = NOW()";
				$UpdateSet[] = "ui.RatioWatchDownload = um.Downloaded";
				$Comment .= ' (Ratio: '.\Format::get_ratio_html($Cur['Uploaded'],
					$Cur['Downloaded'], false).', RR: '.number_format($Cur['RequiredRatio'], 2).')';
			}
			\Tracker::update_tracker('update_user', array('passkey' => $Cur['torrent_pass'],
				'can_leech' => 0));
		}
		$UpdateSet[] = "ui.BanReason = '0'";
		$UpdateSet[] = "um.Enabled = '1'";

		$set = implode(', ', $UpdateSet);

		$this->db->prepared_query("
			UPDATE users_main AS um
				JOIN users_info AS ui ON um.ID = ui.UserID
			SET
				{$set},
				ui.AdminComment = CONCAT('".sqltime()." - ".$Comment."\n\n', ui.AdminComment)
			WHERE
				um.ID = ?", $Cur['ID']);

		return array('enabled' => true, 'user_id' => $Cur['ID'], 'username' => $Cur['Username']);
	}
}
