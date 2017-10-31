<?php

class User extends AbstractAPI {
	private $id = null;
	private $username = null;

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

		switch($_GET['req']) {
			case 'enable':
				return $this->disableUser();
				break;
			case 'disable':
				return $this->enableUser();
				break;
			default:
			case 'stats':
				return $this->getUser();
				break;
		}
	}

	private function getUser() {
		$where = ($this->id !== null) ? "um.ID = '{$this->id}'" : "um.Username = '".db_string($this->username)."'";
		// TODO: add um.BonusPoints,
		$this->db->query("
			SELECT
				um.ID,
				um.Username,
				um.Enabled,
				um.IRCKey,
				um.Uploaded,
				um.Downloaded,
				um.PermissionID AS Class,
				um.Paranoia,
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
				{$where}");

		$user = $this->db->next_record(MYSQLI_ASSOC, array('IRCKey', 'Paranoia'));
		if (!empty($user['Username'])) {
			$user['SecondaryClasses'] = array_map("intval", explode(",", $user['SecondaryClasses']));
			foreach (array('ID', 'Uploaded', 'Downloaded', 'Class', 'Level') as $key) {
				$user[$key] = intval($user[$key]);
			}
			$user['Paranoia'] = unserialize_array($user['Paranoia']);

			$user['Ratio'] = Format::get_ratio($user['Uploaded'], $user['Downloaded']);
			$user['DisplayStats'] = array(
				'Downloaded' => Format::get_size($user['Downloaded']),
				'Uploaded' => Format::get_size($user['Uploaded']),
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
		$this->db->query("UPDATE users_main SET Enabled='2' WHERE ID='{$this->id}'");
		return array('disabled' => true, 'user_id' => $user['ID'], 'username' => $user['Username']);
	}

	private function enableUser() {
		$this->db->query("UPDATE users_main SET Enabled='1' WHERE ID='{$this->id}'");
		return array('enabled' => true, 'user_id' => $user['ID'], 'username' => $user['Username']);
	}
}