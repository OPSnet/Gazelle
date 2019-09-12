<?php

namespace Gazelle\Manager;

class Referral {
	private $db;
	private $cache;
	private $accounts;
	private $proxy;

	public $readOnly;

	const CACHE_ACCOUNTS = 'referral_accounts';
	// Do not change the ordering in this array after launch.
	const ACCOUNT_TYPES = array('Gazelle (API)', 'Gazelle Games', 'Tentacles', 'Luminance', 'Gazelle (HTML)', 'PTP');
	// Accounts which use the user ID instead of username.
	const ID_TYPES = array(3, 4, 5);

	public function __construct($db, $cache) {
		$this->db = $db;
		$this->cache = $cache;
		$this->accounts = $this->cache->get_value(self::CACHE_ACCOUNTS);
		$this->proxy = new \Gazelle\Util\Proxy(REFERRAL_KEY, REFERRAL_BOUNCER);

		if ($this->accounts === false) {
			$this->db->query("SELECT ID, Site, Active, Type FROM referral_accounts");
			$this->accounts = $this->db->has_results() ? $this->db->to_array('ID') : [];
			foreach ($this->accounts as &$acc) {
				$acc["UserIsId"] = in_array($acc["Type"], self::ID_TYPES);
				unset($acc);
			}
			$this->cache->cache_value(self::CACHE_ACCOUNTS, $this->accounts, 86400 * 30);
		}

		$this->readOnly = !apcu_exists('DB_KEY');
	}

	public function generateToken() { 
		return 'OPS|' . \Users::make_secret(64) . '|OPS';
	}

	public function getTypes() {
		return self::ACCOUNT_TYPES;
	}

	public function getAccounts() {
		return $this->accounts;
	}

	public function getActiveAccounts() {
		return array_filter($this->accounts,
			function ($i) { return $i['Active'] == '1' && !$this->readOnly; });
	}

	public function getAccount($id) {
		return array_key_exists($id, $this->accounts) ? $this->accounts[$id] : null;
	}

	public function getFullAccount($id) {
		$this->db->prepared_query("
			SELECT ID, Site, URL, User, Password, Active, Type, Cookie
			FROM referral_accounts
			WHERE ID = ?", $id);

		if ($this->db->has_results()) {
			$account = $this->db->next_record();
			foreach (array('URL', 'User', 'Password', 'Cookie') as $key) {
				if (array_key_exists($key, $account)) {
					$account[$key] = \Gazelle\Util\Crypto::dbDecrypt($account[$key]);
				}
			}
			$account["Cookie"] = json_decode($account["Cookie"], true);
			$account["UserIsId"] = in_array($account["Type"], self::ID_TYPES);
			return $account;
		} else {
			return null;
		}
	}

	public function getFullAccounts() {
		$this->db->prepared_query("
			SELECT ID, Site, URL, User, Password, Active, Type, Cookie
			FROM referral_accounts");

		if ($this->db->has_results()) {
			$accounts = $this->db->to_array('ID', MYSQLI_ASSOC);
			foreach ($accounts as &$account) {
				foreach (array('URL', 'User', 'Password', 'Cookie') as $key) {
					if (array_key_exists($key, $account)) {
						$account[$key] = \Gazelle\Util\Crypto::dbDecrypt($account[$key]);
					}
				}
				$account["Cookie"] = json_decode($account["Cookie"], true);
				$account["UserIsId"] = in_array($account["Type"], self::ID_TYPES);
			}
			return $accounts;
		}
		return [];
	}

	public function createAccount($site, $url, $user, $password, $active, $type, $cookie) {
		if (!$this->readOnly) {
			if (strlen($cookie) < 2) {
				$cookie = '[]';
			}
			json_decode($cookie);
			if (json_last_error() != JSON_ERROR_NONE) {
				$cookie = '[]';
			}
			$this->db->prepared_query("
				INSERT INTO referral_accounts
					(Site, URL, User, Password, Active, Type, Cookie)
				VALUES
					(?, ?, ?, ?, ?, ?, ?)", $site, \Gazelle\Util\Crypto::dbEncrypt($url),
				\Gazelle\Util\Crypto::dbEncrypt($user),	\Gazelle\Util\Crypto::dbEncrypt($password),
				$active, $type, \Gazelle\Util\Crypto::dbEncrypt($cookie));

			$this->cache->delete_value(self::CACHE_ACCOUNTS);
		}
	}

	private function updateCookie($id, $cookie) {
		if (!$this->readOnly) {
			$this->db->prepared_query("
			UPDATE referral_accounts SET
				Cookie = ?
			WHERE ID = ?", \Gazelle\Util\Crypto::dbEncrypt(json_encode($cookie)), $id);
		}
	}

	public function updateAccount($id, $site, $url, $user, $password, $active, $type, $cookie) {
		if (!$this->readOnly) {
			$account = $this->getFullAccount($id);
			if (strlen($cookie) < 2) {
				$cookie = '[]';
			}
			json_decode($cookie);
			if (json_last_error() != JSON_ERROR_NONE) {
				$cookie = '[]';
			}
			if ($cookie == '[]') {
				$cookie = json_encode($account["Cookie"]);
			}
			if (strlen($password) == 0) {
				$password = $account["Password"];
			}
			$this->db->prepared_query("
				UPDATE referral_accounts SET
					Site = ?,
					URL = ?,
					User = ?,
					Password = ?,
					Active = ?,
					Type = ?,
					Cookie = ?
				WHERE ID = ?", $site, \Gazelle\Util\Crypto::dbEncrypt($url),
				\Gazelle\Util\Crypto::dbEncrypt($user),	\Gazelle\Util\Crypto::dbEncrypt($password),
				$active, $type, \Gazelle\Util\Crypto::dbEncrypt($cookie), $id);

			$this->cache->delete_value(self::CACHE_ACCOUNTS);
		}
	}

	public function deleteAccount($id) {
		$this->db->prepared_query("DELETE FROM referral_accounts WHERE ID = ?", $id);

		$this->cache->delete_value(self::CACHE_ACCOUNTS);
	}

	public function getReferredUsers($startDate, $endDate, $site, $username, $invite, $limit, $view) {
		if ($startDate == NULL) {
			$startDate = \Gazelle\Util\Time::timeOffset(-(3600 * 24 * 30), true);
		}

		if ($endDate == NULL) {
			$endDate = \Gazelle\Util\Time::sqlTime();
		}

		$Filter = ['ru.Created BETWEEN ? AND ?'];
		$Params = [$startDate, $endDate];

		if ($view === 'pending') {
			$Filter[] = 'ru.Active = 0';
		} else if ($view === 'processed') {
			$Filter[] = 'ru.Active = 1';
		}

		if ($site != NULL) {
			$Filter[] = 'ru.Site LIKE ?';
			$Params[] = $site;
		}

		if ($username != NULL) {
			$Filter[] = '(ru.Username LIKE ? OR um.Username LIKE ?)';
			$Params[] = $username;
			$Params[] = $username;
		}

		if ($invite != NULL) {
			$Filter[] = 'ru.InviteKey LIKE ?';
			$Params[] = $invite;
		}

		$Filter = implode(' AND ', $Filter);

		$qId = $this->db->prepared_query("
			SELECT SQL_CALC_FOUND_ROWS ru.ID, ru.UserID, ru.Site, ru.Username, ru.Created, ru.Joined, ru.IP, ru.Active, ru.InviteKey
			FROM referral_users ru
			LEFT JOIN users_main um ON um.ID = ru.UserID
			WHERE $Filter
			ORDER BY ru.Created DESC
			LIMIT $limit", ...$Params);
		$this->db->prepared_query("SELECT FOUND_ROWS()");
		list($Results) = $this->db->next_record();
		$this->db->set_query_id($qId);

		$Users = $Results > 0 ? $this->db->to_array('ID', MYSQLI_ASSOC) : [];

		return array("Results" => $Results, "Users" => $Users);
	}

	public function deleteUserReferral($id) {
		$this->db->prepared_query("
			DELETE FROM referral_users
			WHERE ID = ?",
			$id);
	}

	public function validateCookie($acc) {
		switch ($acc["Type"]) {
			case 0:
				return $this->validateGazelleCookie($acc);
				break;
			case 1:
				return true;
				break;
			case 2:
				return $this->validateTentacleCookie($acc);
				break;
			case 3:
			case 4:
			case 5:
				return $this->validateLuminanceCookie($acc);
				break;
		}
		return false;
	}

	private function validateGazelleCookie($acc) {
		$url  = $acc["URL"] . 'ajax.php';

		$result = $this->proxy->fetch($url, array("action" => "index"), $acc["Cookie"], false);
		$json = json_decode($result["response"], true);

		return $json["status"] === 'success';
	}

	private function validateTentacleCookie($acc) {
		$url = $acc["URL"];

		$result = $this->proxy->fetch($url, array(), $acc["Cookie"], false);
		$match = strpos($result["response"], "authKey:");

		return $match !== false;
	}

	private function validateLuminanceCookie($acc) {
		$url = $acc["URL"];

		$result = $this->proxy->fetch($url, array(), $acc["Cookie"], false);
		$match = strpos($result["response"], "authkey");

		return $match !== false;
	}

	public function loginAccount(&$acc) {
		switch ($acc["Type"]) {
			case 0:
				return $this->loginGazelleAccount($acc);
				break;
			case 1:
				return true;
				break;
			case 2:
				return $this->loginTentacleAccount($acc);
				break;
			case 3:
				return $this->loginLuminanceAccount($acc);
				break;
			case 4:
				return $this->loginGazelleHTMLAccount($acc);
				break;
			case 5:
				return $this->loginPTPAccount($acc);
				break;
		}
		return false;
	}

	private function loginGazelleAccount(&$acc) {
		if ($this->validateGazelleCookie($acc)) {
			return true;
		}

		$url = $acc["URL"] . "login.php";

		$result = $this->proxy->fetch($url, array("username" => $acc["User"],
			"password" => $acc["Password"], "keeplogged" => "1"), array(), true);

		if ($result["status"] == 200) {
			$acc["Cookie"] = $result["cookies"];
			$this->updateCookie($acc["ID"], $acc["Cookie"]);
		}

		return $result["status"] == 200;
	}

	private function loginTentacleAccount(&$acc) {
		if ($this->validateTentacleCookie($acc)) {
			return true;
		}

		$url = $acc["URL"] . "user/login";

		$result = $this->proxy->fetch($url, array("username" => $acc["User"],
			"password" => $acc["Password"], "keeplogged" => "1"), array(), true);

		if ($result["status"] == 200) {
			$acc["Cookie"] = $result["cookies"];
			$this->updateCookie($acc["ID"], $acc["Cookie"]);
		}

		return $result["status"] == 200;
	}

	private function loginLuminanceAccount(&$acc) {
		if ($this->validateLuminanceCookie($acc)) {
			return true;
		}

		$url = $acc["URL"] . "login";

		$result = $this->proxy->fetch($url, array(), array(), false);
		$doc = new \DOMDocument();
		libxml_use_internal_errors(true);
		@$doc->loadHTML($result["response"]);
		$xpath = new \DOMXPath($doc);
		$token = $xpath->evaluate("string(//input[@name='token']/@value)");

		$result = $this->proxy->fetch($url, array("username" => $acc["User"],
			"password" => $acc["Password"], "keeploggedin" => "1",
			"token" => $token, "cinfo" => "1024|768|24|0",
			"iplocked" => "1"), $result["cookies"], true);

		if ($result["status"] == 200) {
			$acc["Cookie"] = $result["cookies"];
			$this->updateCookie($acc["ID"], $acc["Cookie"]);
		}

		return $result["status"] == 200;
	}

	private function loginGazelleHTMLAccount(&$acc) {
		if ($this->validateLuminanceCookie($acc)) {
			return true;
		}

		$url = $acc["URL"] . "login.php";

		$result = $this->proxy->fetch($url, array("username" => $acc["User"],
			"password" => $acc["Password"], "keeplogged" => "1"), array(), true);

		if ($result["status"] == 200) {
			$acc["Cookie"] = $result["cookies"];
			$this->updateCookie($acc["ID"], $acc["Cookie"]);
		}

		return $result["status"] == 200;
	}

	private function loginPTPAccount(&$acc) {
		if ($this->validateLuminanceCookie($acc)) {
			return true;
		}

		$url = $acc["URL"] . "login_finish.php";

		$result = $this->proxy->fetch($url, array("username" => $acc["User"],
			"password" => $acc["Password"], "keeplogged" => "1"), array(), true);

		if ($result["status"] == 200) {
			$acc["Cookie"] = $result["cookies"];
			$this->updateCookie($acc["ID"], $acc["Cookie"]);
		}

		return $result["status"] == 200;
	}

	public function verifyAccount($acc, $user, $key) {
		switch ($acc["Type"]) {
			case 0:
				return $this->verifyGazelleAccount($acc, $user, $key);
				break;
			case 1:
				return $this->verifyGGNAccount($acc, $user, $key);
				break;
			case 2:
				return $this->verifyTentacleAccount($acc, $user, $key);
				break;
			case 3:
				return $this->verifyLuminanceAccount($acc, $user, $key);
				break;
			case 4:
				return $this->verifyGazelleHTMLAccount($acc, $user, $key);
				break;
			case 5:
				return $this->verifyPTPAccount($acc, $user, $key);
				break;
		}
		return "Unrecognised account type";
	}

	private function verifyGazelleAccount($acc, $user, $key) {
		if (!$this->loginGazelleAccount($acc)) {
			return "Internal error";
		}

		$url = $acc["URL"] . 'ajax.php';

		$result = $this->proxy->fetch($url, array("action" => "usersearch", "search" => $user),
			$acc["Cookie"], false);
		$json = json_decode($result["response"], true);

		if ($json["status"] === 'success') {
			$match = false;
			foreach ($json["response"]["results"] as $userResult) {
				if ($userResult["username"] == $user) {
					$match = true;
					$userId = $userResult["userId"];
					break;
				}
			}

			if ($match) {
				$result = $this->proxy->fetch($url, array("action" => "user", "id" => $userId),
					$acc["Cookie"], false);
				$json = json_decode($result["response"], true);

				$profile = $json["response"]["profileText"];
				$match = strpos($profile, $key);

				if ($match !== false) {
					return true;
				} else {
					return "Token not found. Please try again.";
				}
			}
		}

		return "Token not found. Please try again.";
	}

	private function verifyGGNAccount($acc, $user, $key) {
		$url = $acc["URL"] . 'api.php';

		$result = $this->proxy->fetch($url, array("request" => "user", "name" => $user,
			"key" => $acc["Password"]), array(), false);
		$json = json_decode($result["response"], true);

		$profile = $json["response"]["profileText"];
		$match = strpos($profile, $key);

		if ($match !== false) {
			return true;
		} else {
			return "Token not found. Please try again.";
		}
	}

	private function verifyTentacleAccount($acc, $user, $key) {
		if (!$this->loginTentacleAccount($acc)) {
			return "Internal error";
		}

		$url = $acc["URL"] . 'user/profile/' . $user;

		$result = $this->proxy->fetch($url, array(), $acc["Cookie"], false);

		$profile = $result["response"];
		$match = strpos($profile, $key);

		if ($match !== false) {
			return true;
		} else {
			return "Token not found. Please try again.";
		}
	}

	private function verifyLuminanceAccount($acc, $user, $key) {
		if (!$this->loginLuminanceAccount($acc)) {
			return "Internal error";
		}

		$url = $acc["URL"] . 'user.php';

		$result = $this->proxy->fetch($url, array("id" => $user), $acc["Cookie"], false);

		$profile = $result["response"];
		$match = strpos($profile, $key);

		if ($match !== false) {
			return true;
		} else {
			return "Token not found. Please try again.";
		}
	}

	private function verifyGazelleHTMLAccount($acc, $user, $key) {
		if (!$this->loginGazelleHTMLAccount($acc)) {
			return "Internal error";
		}

		$url = $acc["URL"] . 'user.php';

		$result = $this->proxy->fetch($url, array("id" => $user),
			$acc["Cookie"], false);

		$profile = $result["response"];
		$match = strpos($profile, $key);

		if ($match !== false) {
			return true;
		} else {
			return "Token not found. Please try again.";
		}
	}

	private function verifyPTPAccount($acc, $user, $key) {
		if (!$this->loginPTPAccount($acc)) {
			return "Internal error";
		}

		$url = $acc["URL"] . 'user.php';

		$result = $this->proxy->fetch($url, array("id" => $user),
			$acc["Cookie"], false);

		$profile = $result["response"];
		$match = strpos($profile, $key);

		if ($match !== false) {
			return true;
		} else {
			return "Token not found. Please try again.";
		}
	}

	public function generateInvite($acc, $username, $email) {
		$this->db->prepared_query("
			SELECT Username
			FROM referral_users
			WHERE Username = ? AND Site = ?",
			$username, $acc["Site"]);
		if ($this->db->has_results()) {
			return [false, "Account already used for referral, join " . BOT_DISABLED_CHAN . " on " . BOT_SERVER . " for help."];
		}

		$InviteExpires = time_plus(60 * 60 * 24 * 3); // 3 days
		$InviteReason = 'This user was referred from their account on ' . $acc["Site"] . '.';
		$InviteKey = db_string(\Users::make_secret());

		// save invite to DB
		$this->db->prepared_query("
			INSERT INTO invites
				(InviterID, InviteKey, Email, Expires, Reason)
			VALUES
				(?, ?, ?, ?, ?)",
			0, $InviteKey, $email, $InviteExpires, $InviteReason);

		// save to referral history
		$this->db->prepared_query("
			INSERT INTO referral_users
				(Username, Site, IP, InviteKey)
			VALUES
				(?, ?, ?, ?)",
			$username, $acc["Site"], $_SERVER["REMOTE_ADDR"], $InviteKey);

		if (defined('REFERRAL_SEND_EMAIL') && REFERRAL_SEND_EMAIL) {
			require(SERVER_ROOT . '/classes/templates.class.php');
			$Tpl = new \TEMPLATE;
			$Tpl->open(SERVER_ROOT . '/templates/referral.tpl'); // Password reset template
			$Tpl->set('Email', $email);
			$Tpl->set('InviteKey', $InviteKey);
			$Tpl->set('DISABLED_CHAN', BOT_DISABLED_CHAN);
			$Tpl->set('IRC_SERVER', BOT_SERVER);
			$Tpl->set('SITE_NAME', SITE_NAME);
			$Tpl->set('SITE_URL', SITE_URL);
			// send email
			\Misc::send_email($email, 'You have been invited to ' . SITE_NAME, $Tpl->get(), 'noreply', 'text/plain');
		}

		return [true, $InviteKey];
	}
}
