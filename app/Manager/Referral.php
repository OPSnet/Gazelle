<?php

namespace Gazelle\Manager;

use Gazelle\Util\Crypto;
use Gazelle\Util\Proxy;

class Referral extends \Gazelle\Base {
    private $accounts;
    private $proxy;

    public $readOnly;

    const CACHE_ACCOUNTS = 'referral_accounts';
    const CACHE_BOUNCER = 'bouncer_status';
    // Do not change the ordering in this array after launch.
    const ACCOUNT_TYPES = ['Gazelle (API)', '', '', 'Luminance', 'Gazelle (HTML)', ''];
    // Accounts which use the user ID instead of username.
    const ID_TYPES = [3, 4, 5];

    public function __construct() {
        parent::__construct();
        $this->accounts = $this->cache->get_value(self::CACHE_ACCOUNTS);
        $this->proxy = new Proxy(REFERRAL_KEY, REFERRAL_BOUNCER);

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

        if (!$this->readOnly) {
            $url = $this->db->scalar("SELECT URL FROM referral_accounts LIMIT 1");
            if ($url) {
                $this->readOnly = Crypto::dbDecrypt($url) == null;
            }
        }
    }

    public function checkBouncer() {
        if (!count($this->accounts)) {
            return true;
        }

        $status = $this->cache->get_value(self::CACHE_BOUNCER);
        if ($status === false) {
            $req = $this->proxy->fetch(site_url(), [], [], false);
            $status = $req == null ? 'dead' : 'alive';
            $this->cache->cache_value(self::CACHE_BOUNCER, $status, 60 * 15);
        }

        return $status == 'alive';
    }

    public function generateToken() {
        return 'OPS|' . randomString(64) . '|OPS';
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
            WHERE ID = ?
            ", $id
        );

        $account = null;
        if ($this->db->has_results()) {
            $account = $this->db->next_record();
            foreach (['URL', 'User', 'Password', 'Cookie'] as $key) {
                if (array_key_exists($key, $account)) {
                    $account[$key] = Crypto::dbDecrypt($account[$key]);
                }
            }
            $account["Cookie"] = json_decode($account["Cookie"], true);
            $account["UserIsId"] = in_array($account["Type"], self::ID_TYPES);
        }

        return $account;
    }

    public function getFullAccounts() {
        $this->db->prepared_query("
            SELECT ID, Site, URL, User, Password, Active, Type, Cookie
            FROM referral_accounts");

        if ($this->db->has_results()) {
            $accounts = $this->db->to_array('ID', MYSQLI_ASSOC);
            foreach ($accounts as &$account) {
                foreach (['URL', 'User', 'Password', 'Cookie'] as $key) {
                    if (array_key_exists($key, $account)) {
                        $account[$key] = Crypto::dbDecrypt($account[$key]);
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
        if ($this->readOnly) {
            return;
        }

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
                (?,    ?,   ?,    ?,        ?,      ?,    ?)
            ", $site, Crypto::dbEncrypt($url), Crypto::dbEncrypt($user),
            Crypto::dbEncrypt($password), $active, $type, Crypto::dbEncrypt($cookie)
        );

        $this->cache->delete_value(self::CACHE_ACCOUNTS);
    }

    private function updateCookie($id, $cookie) {
        if ($this->readOnly) {
            return;
        }

        $this->db->prepared_query("
            UPDATE referral_accounts
            SET Cookie = ?
            WHERE ID = ?
            ", Crypto::dbEncrypt(json_encode($cookie)), $id
        );
    }

    public function updateAccount($id, $site, $url, $user, $password, $active, $type, $cookie) {
        if ($this->readOnly) {
            return;
        }

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
            WHERE ID = ?
            ", $site, Crypto::dbEncrypt($url), Crypto::dbEncrypt($user),
            Crypto::dbEncrypt($password), $active, $type, Crypto::dbEncrypt($cookie), $id
        );

        $this->cache->delete_value(self::CACHE_ACCOUNTS);
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

        $filter = ['ru.Created BETWEEN ? AND ?'];
        $params = [$startDate, $endDate];

        if ($view === 'pending') {
            $filter[] = 'ru.Active = 0';
        } else if ($view === 'processed') {
            $filter[] = 'ru.Active = 1';
        }

        if ($site != NULL) {
            $filter[] = 'ru.Site LIKE ?';
            $params[] = $site;
        }

        if ($username != NULL) {
            $filter[] = '(ru.Username LIKE ? OR um.Username LIKE ?)';
            $params[] = $username;
            $params[] = $username;
        }

        if ($invite != NULL) {
            $filter[] = 'ru.InviteKey LIKE ?';
            $params[] = $invite;
        }

        $filter = implode(' AND ', $filter);

        $this->db->prepared_query("
            SELECT SQL_CALC_FOUND_ROWS ru.ID, ru.UserID, ru.Site, ru.Username, ru.Created, ru.Joined, ru.IP, ru.Active, ru.InviteKey
            FROM referral_users ru
            LEFT JOIN users_main um ON um.ID = ru.UserID
            WHERE $filter
            ORDER BY ru.Created DESC
            LIMIT $limit
            ", ...$params
        );

        $results = $this->db->scalar("SELECT found_rows()");

        $users = $results > 0 ? $this->db->to_array('ID', MYSQLI_ASSOC) : [];

        return ["Results" => $results, "Users" => $users];
    }

    public function deleteUserReferral($id) {
        $this->db->prepared_query("
            DELETE FROM referral_users
            WHERE ID = ?
            ", $id
        );
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
                return false;
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

        $result = $this->proxy->fetch($url, ["action" => "index"], $acc["Cookie"], false);
        $json = json_decode($result["response"], true);

        return $json["status"] === 'success';
    }

    private function validateLuminanceCookie($acc) {
        $url = $acc["URL"];

        $result = $this->proxy->fetch($url, [], $acc["Cookie"], false);
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
                return false;
            case 3:
                return $this->loginLuminanceAccount($acc);
                break;
            case 4:
                return $this->loginGazelleHTMLAccount($acc);
                break;
            case 5:
                return false;
        }
        return false;
    }

    private function loginGazelleAccount(&$acc) {
        if ($this->validateGazelleCookie($acc)) {
            return true;
        }

        $url = $acc["URL"] . "login.php";

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeplogged" => "1"], [], true);

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

        $result = $this->proxy->fetch($url, [], [], false);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$doc->loadHTML($result["response"]);
        $xpath = new \DOMXPath($doc);
        $token = $xpath->evaluate("string(//input[@name='token']/@value)");

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeploggedin" => "1",
            "token" => $token, "cinfo" => "1024|768|24|0",
            "iplocked" => "1"], $result["cookies"], true);

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

        $result = $this->proxy->fetch($url, ["username" => $acc["User"],
            "password" => $acc["Password"], "keeplogged" => "1"], [], true);

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
                return false;
            case 2:
                return false;
            case 3:
                return $this->verifyLuminanceAccount($acc, $user, $key);
                break;
            case 4:
                return $this->verifyGazelleHTMLAccount($acc, $user, $key);
                break;
            case 5:
                return false;
        }
        return "Unrecognised account type";
    }

    private function verifyGazelleAccount($acc, $user, $key) {
        if (!$this->loginGazelleAccount($acc)) {
            return "Internal error 10";
        }

        $url = $acc["URL"] . 'ajax.php';

        $result = $this->proxy->fetch($url, ["action" => "usersearch", "search" => $user],
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
                $result = $this->proxy->fetch($url, ["action" => "user", "id" => $userId],
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

    private function verifyLuminanceAccount($acc, $user, $key) {
        if (!$this->loginLuminanceAccount($acc)) {
            return "Internal error 12";
        }

        $url = $acc["URL"] . 'user.php';

        $result = $this->proxy->fetch($url, ["id" => $user], $acc["Cookie"], false);

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
            return "Internal error 13";
        }

        $url = $acc["URL"] . 'user.php';

        $result = $this->proxy->fetch($url, ["id" => $user],
            $acc["Cookie"], false);

        $profile = $result["response"];
        $match = strpos($profile, $key);

        if ($match !== false) {
            return true;
        } else {
            return "Token not found. Please try again.";
        }
    }

    public function generateInvite($acc, $username, $email, $twig) {
        $existing = $this->db->scalar("
            SELECT Username
            FROM referral_users
            WHERE Username = ? AND Site = ?
            ", $username, $acc["Site"]
        );

        if ($existing) {
            return [false, "Account already used for referral, join " . BOT_DISABLED_CHAN . " on " . BOT_SERVER . " for help."];
        }

        $inviteKey = randomString();
        $this->db->prepared_query("
            INSERT INTO invites
                   (InviteKey, Email, Reason, Expires)
            VALUES (?,         ?,     ?,      now() + INTERVAL 3 DAY)
            ", $inviteKey, $email,
                'This user was referred from their account on ' . $acc["Site"] . '.'
        );

        $this->db->prepared_query("
            INSERT INTO referral_users
                (Username, Site, IP, InviteKey)
            VALUES
                (?,        ?,    ?,  ?)
            ", $username, $acc["Site"], $_SERVER["REMOTE_ADDR"], $inviteKey
        );

        if (defined('REFERRAL_SEND_EMAIL') && REFERRAL_SEND_EMAIL) {
            $message = $twig->render('emails/referral.twig', [
                'Email' => $email,
                'InviteKey' => $inviteKey,
                'DISABLED_CHAN' => BOT_DISABLED_CHAN,
                'IRC_SERVER' => BOT_SERVER,
                'SITE_NAME' => SITE_NAME,
                'SITE_URL' => SITE_URL
            ]);

            \Misc::send_email($email, 'You have been invited to ' . SITE_NAME, $message, 'noreply', 'text/plain');
        }

        return [true, $inviteKey];
    }
}
