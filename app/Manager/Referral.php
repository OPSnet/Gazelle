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
	const ACCOUNT_TYPES = array('Gazelle (API)', 'Gazelle Games', 'Tentacles', 'Luminance');

	public function __construct($db, $cache) {
		$this->db = $db;
		$this->cache = $cache;
		$this->accounts = $this->cache->get_value(self::CACHE_ACCOUNTS);
		$this->proxy = new \Gazelle\Util\Proxy(REFERRAL_KEY, REFERRAL_BOUNCER);

		if ($this->accounts === false) {
			$this->db->query("SELECT ID, Site, Active, Type FROM referral_accounts");
			$this->accounts = $this->db->has_results() ? $this->db->to_array('ID') : [];
			foreach ($this->accounts as &$acc) {
				$acc["UserIsId"] = $acc["Type"] == 3;
				unset($acc);
			}
			$this->cache->cache_value(self::CACHE_ACCOUNTS, $this->accounts, 86400 * 30);
		}

		$this->readOnly = !apcu_exists('DB_KEY');
	}

	public function generateToken() { 
		return 'APL:' . \Users::make_secret(64) . ':APL';
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
			}
			return $accounts;
		}
		return [];
	}

	public function createAccount($site, $url, $user, $password, $active, $type) {
		if (!$this->readOnly) {
			$this->db->prepared_query("
				INSERT INTO referral_accounts
					(Site, URL, User, Password, Active, Type, Cookie)
				VALUES
					(?, ?, ?, ?, ?, ?, ?)", $site, \Gazelle\Util\Crypto::dbEncrypt($url),
				\Gazelle\Util\Crypto::dbEncrypt($user),	\Gazelle\Util\Crypto::dbEncrypt($password),
				$active, $type, \Gazelle\Util\Crypto::dbEncrypt('[]'));

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

	public function updateAccount($id, $site, $url, $user, $password, $active, $type) {
		if (!$this->readOnly) {
			$account = $this->getFullAccount($id);
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
					Type = ?
				WHERE ID = ?", $site, \Gazelle\Util\Crypto::dbEncrypt($url),
				\Gazelle\Util\Crypto::dbEncrypt($user),	\Gazelle\Util\Crypto::dbEncrypt($password),
				$active, $type, $id);

			$this->cache->delete_value(self::CACHE_ACCOUNTS);
		}
	}

	public function deleteAccount($id) {
		$this->db->prepared_query("DELETE FROM referral_accounts WHERE ID = ?", $id);

		$this->cache->delete_value(self::CACHE_ACCOUNTS);
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
		$match = strpos($result["response"], "authkey:");

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
		if ($this->validateTentacleAccount($acc)) {
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
		if ($this->validateLuminanceAccount($acc)) {
			return true;
		}

		$url = $acc["URL"] . "login";

		$result = $this->proxy->fetch($url, array("username" => $acc["User"],
			"password" => $acc["Password"], "keeploggedin" => "1"), array(), true);

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

		return "User not found. Please try again.";
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

		$url = $acc["URL"] . 'user' . $user;

		$result = $this->proxy->fetch($url, array("id" => $acc["Username"]), $acc["Cookie"], false);

		$profile = $result["response"];
		$match = strpos($profile, $key);

		if ($match !== false) {
			return true;
		} else {
			return "Token not found. Please try again.";
		}
	}

	public function generateInvite($acc, $username, $email) {
		$InviteExpires = time_plus(60 * 60 * 24 * 3); // 3 days
		$InviteReason = 'This user was referred to membership by their account ' . $username .
		   ' at ' . $acc["Site"] . '. They verified their account on ' . date('Y-m-d H:i:s');
		$InviteKey = db_string(\Users::make_secret());
		require(SERVER_ROOT . '/classes/templates.class.php');
		$Tpl = new \TEMPLATE;
		$Tpl->open(SERVER_ROOT . '/templates/referral.tpl'); // Password reset template
		$Tpl->set('Email', $email);
		$Tpl->set('InviteKey', $InviteKey);
		$Tpl->set('DISABLED_CHAN', BOT_DISABLED_CHAN);
		$Tpl->set('IRC_SERVER', BOT_SERVER);
		$Tpl->set('SITE_NAME', SITE_NAME);
		$Tpl->set('SITE_URL', SITE_URL);

		// save invite to DB
		$this->db->prepared_query("
			INSERT INTO invites
				(InviterID, InviteKey, Email, Expires, Reason)
			VALUES
				(?, ?, ?, ?, ?)",
			0, $InviteKey, $email, $InviteExpires, $InviteReason);

		// send email
		\Misc::send_email($email, 'You have been invited to ' . SITE_NAME, $Tpl->get(), 'noreply', 'text/plain');

		return $InviteKey;
	}
}
