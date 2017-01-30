<?php
/**
 * Class Referral
 * @author prnd
 * @created 2017-01-02
 *
 */
class Referral {

    // array to store external site credentials and API URIs, stored in cache to keep user sessions alive
    private $ExternalServices;
    // set session length
    private $CookieExpiry = 604800; // 1 week

    /**
     * Constructor
     *
     *
     * @author prnd
     * @created 2017-01-02
     */
    function __construct() {
        // populate services array from cache if it exists, if not then grab from template
        $services_config = $GLOBALS['ExternalServicesConfig'];
        $this->ExternalServices = G::$Cache->get_value('referral_services');
        // grab from template if not in cache
        if (empty($this->ExternalServices)) {
            $this->ExternalServices = $GLOBALS['ExternalServicesConfig'];
            $this->cache_services();
        } else {
            // make sure we get fresh credentials from config
            foreach ($services_config as $service => $config) {
                // copy entire thing if it's new to the cache
                if (!in_array($service, $this->ExternalServices)) {
                    $this->ExternalServices[$service] = $config;
                } else {
                    // else just grab new creds
                    $this->ExternalServices[$service]['username'] = $config['username'];
                    $this->ExternalServices[$service]['password'] = $config['password'];
                }
            }
        }
        // use php session for lack of better solution
        session_start();
        // check for curl
        if (!function_exists('curl_version')) {
            die('cURL is unavailable on this server.');
        }
    }

    /**
     * Services List Method
     *
     * Returns an array of available services for referral actions
     *
     * @author prnd
     * @created 2017-01-02
     * @return array of available services
     */
    public function services_list() {
        foreach ($this->ExternalServices as $key => $val) {
            // check if service is up and enabled
            if ($val['status'] === TRUE && $this->service_is_up($key)) {
                $response[] = $key;
            }
        }
        return $response;
    }

    /**
     * Generates a unique token for referral verification
     *
     * @return generated token
     */
    public function generate_token() {

        $_SESSION['referral_token'] = 'APL:' . Users::make_secret(64) . ':APL';
        return $_SESSION['referral_token'];
    }

    /**
     * Login Method, points to relevant login method based on service type
     *
     * @param $service
     * @return bool
     */
    private function login($service) {
        switch ($this->ExternalServices[$service]['type']) {

            case 'gazelle':
                return $this->gazelle_login($service);
                break;
            default:
                die("Invalid External Service");
        }
    }

    /**
     * Verify Method, verifies via API calls that a user has an account at the external service based on the service type.
     *
     * @param $service
     * @param $username
     * @return bool
     */
    public function verify($service, $username) {

        switch ($this->ExternalServices[$service]['type']) {

            case 'gazelle':
                return $this->gazelle_verify($service, $username);
                break;
            default:
                die("Invalid External Service");
        }
    }


    /**
     * Checks if a service called by name returns 200
     *
     * @author prnd
     * @param $service
     * @return bool
     */
    private function service_is_up($service) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        // use local curl object as we're only getting the headers back
        $curl = curl_init($this->ExternalServices[$service]['base_url']);
        //set to HEAD request only
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        // do the request
        curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $code == 200;
    }

    /**
     * Method to gain access to the external gazelle API, or affirm that we still have a session active.
     *
     * @param $service
     * @return bool
     */
    private function gazelle_login($service) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        //check if cookie is still valid
        if ($this->gazelle_valid_session($service)) {
            //cookie is valid, so we can continue making requests to the API
            return TRUE;
        } else {
            $this->ExternalServices[$service]['cookie'] = '';
            $this->ExternalServices[$service]['cookie_expiry'] = 0;
        }
        // lets get a cookie from the login service then
        $login_fields = [
            'username' => $this->ExternalServices[$service]['username'],
            'password' => $this->ExternalServices[$service]['password'],
            'keeplogged' => 1
        ];
        $ch = curl_init();
        $this->set_curl($ch);
        curl_setopt($ch, CURLOPT_URL, $this->ExternalServices[$service]['base_url'] . $this->ExternalServices[$service]['login_path']);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($login_fields));
        // do el requesto
        $result = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        //check for session cookie, as it is the indicator of success
        $cookies = $this->parse_cookies($result, $header_size);
        if (array_key_exists('session', $cookies)) {
            $this->ExternalServices[$service]['cookie'] = urlencode($cookies['session']);
            $this->ExternalServices[$service]['cookie_expiry'] = time() + $this->CookieExpiry;
            $this->cache_services();
            return TRUE;
        } else {
            return FALSE;
        }

    }

    /**
     * Method to verify active user session at external gazelle service
     * @param $service
     * @return bool
     */
    private function gazelle_valid_session($service) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        $url = $this->ExternalServices[$service]['base_url'];
        $url .= $this->ExternalServices[$service]['api_path'];
        $url .= 'index';
        // do la requesta
        $ch = curl_init($url);
        $this->set_curl($ch);
        curl_setopt($ch, CURLOPT_COOKIE, 'session=' . $this->ExternalServices[$service]['cookie']);
        $result = curl_exec($ch);
        curl_close($ch);
        // toss json results into array
        $result = json_decode($result, TRUE);
        if ($result['status'] === 'success') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Method to verify that a username exists at a given external gazelle service
     * Calls gazelle_verify_token to verify token is in place.
     *
     * @param $service
     * @param $username
     * @return bool
     */
    private function gazelle_verify($service, $username) {
        $match = FALSE;
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        // login to ensure that we haven't expired out session with the external service
        $this->login($service);
        // build usersearch url
        $url = $this->ExternalServices[$service]['base_url'];
        $url .= $this->ExternalServices[$service]['api_path'];
        $url .= 'usersearch';
        $url .= '&search=' . $username;
        // do la requesta
        $ch = curl_init($url);
        $this->set_curl($ch);
        curl_setopt($ch, CURLOPT_COOKIE, 'session=' . $this->ExternalServices[$service]['cookie']);
        $result = curl_exec($ch);
        curl_close($ch);
        // toss json results into array
        $result = json_decode($result, TRUE);
        // fail with error if we get an error message
        if ($result['status'] === 'failure') {
            die('Error: Try again - ' . $result['error']);
        } elseif ($result['status'] !== 'success') {
            die('Error: Try again later');
        }
        $user_results = $result['response']['results'];
        if (count($user_results) == 0) {
            $_SESSION['verify_error'] = "User Not Found, Please Try Again";
            return FALSE;
        }
        foreach ($user_results as $user_result) {
            if ($user_result['username'] === $username) {
                $match = TRUE;
                $user_id = $user_result['userId'];
                break;
            }
        }
        // we match it, then verify token
        if ($match) {
            return $this->gazelle_verify_token($service, $user_id);
        } else {
            $_SESSION['verify_error'] = "User Not Found, Please Try Again";
            return FALSE;
        }
    }

    /**
     * Method to verify that the generated token has been added to the body of the external tracker users profile.
     *
     * @param $service
     * @param $user_id
     * @return bool
     */
    private function gazelle_verify_token($service, $user_id) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        // login to ensure that we haven't expired out session with the external service
        $this->login($service);
        // build user profile url
        $url = $this->ExternalServices[$service]['base_url'];
        $url .= $this->ExternalServices[$service]['api_path'];
        $url .= 'user';
        $url .= '&id=' . $user_id;
        // do la requesta
        $ch = curl_init($url);
        $this->set_curl($ch);
        curl_setopt($ch, CURLOPT_COOKIE, 'session=' . $this->ExternalServices[$service]['cookie']);
        $result = curl_exec($ch);
        curl_close($ch);
        // toss json results into array
        $result = json_decode($result, TRUE);
        // fail with error if we get an error message
        if ($result['status'] === 'failure') {
            die('Error: Try again - ' . $result['error']);
        } elseif ($result['status'] !== 'success') {
            die('Error: Try again later');
        }
        // grab profile text from response
        $user_profile_text = $result['response']['profileText'];
        // let's get a match
        $match = strpos($user_profile_text, $_SESSION['referral_token']);
        if ($match !== FALSE) {
            return TRUE;
        } else {
            $_SESSION['verify_error'] = "Token Not Found, Please Try Again";
            return FALSE;
        }
    }

    /**
     * The thing you have to do to get a parsed set of cookies from a cURL request in PHP
     *
     * @param $result result of a curl post response
     * @return array - an array of cookies set by the request
     */
    private function parse_cookies($result) {
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
        $cookies = array();
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        return $cookies;
    }

    /**
     * This method creates an invite and sends it to the specified email address.
     * This borrows heavily from the /sections/user/take_invite.php file
     * I am so sorry if you are the one refactoring the invite functionality.
     *
     * @author prnd
     * @param $service
     * @param $email
     * @param $username
     * @return bool
     */
    public function create_invite($service, $email, $username) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }

        // form invite data and populate template
        $InviteExpires = time_plus(60 * 60 * 24 * 3); // 3 days
        $InviteReason = 'This user was referred to membership by their account at ' . $service . '. They verified their account on ' . date('Y-m-d H:i:s');
        $InviteKey = db_string(Users::make_secret());
        $InviterId = $this->ExternalServices[$service]['inviter_id'];
        require(SERVER_ROOT . '/classes/templates.class.php');
        $Tpl = NEW TEMPLATE;
        $Tpl->open(SERVER_ROOT . '/templates/referral.tpl'); // Password reset template
        $Tpl->set('Email', $email);
        $Tpl->set('InviteKey', $InviteKey);
        $Tpl->set('DISABLED_CHAN', BOT_DISABLED_CHAN);
        $Tpl->set('IRC_SERVER', BOT_SERVER);
        $Tpl->set('SITE_NAME', SITE_NAME);
        $Tpl->set('SITE_URL', SITE_URL);

        // save invite to DB
        G::$DB->query("
		INSERT INTO invites
			(InviterID, InviteKey, Email, Expires, Reason)
		VALUES
			('$InviterId', '$InviteKey', '".db_string($email)."', '$InviteExpires', '$InviteReason')");

        // send email
        Misc::send_email($email, 'You have been invited to ' . SITE_NAME, $Tpl->get(), 'noreply', 'text/plain');
    }

    /**
     * Saves the external services array to the global cache.
     */
    private function cache_services() {
        G::$Cache->cache_value('referral_services', $this->ExternalServices);
    }

    /**
     * Inits and sets defaults for curl object
     */
    private function set_curl($ch) {

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    }
}