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
    // template for external services array
    private $ExternalServicesTemplate = [
        "APOLLO" => [
            'type' => 'gazelle',
            'base_url' => 'https://apollo.rip/',
            'api_path' => 'ajax.php?action=',
            'login_path' => 'login.php',
            'username' => 'prnd',
            'password' => 'zxJe*65*E^X^3w6MP5ed',
            'cookie' => '',
            'cookie_expiry' => 0,
            'status' => TRUE
        ],
        "VagrantGazelle" => [
            'type' => 'gazelle',
            'base_url' => 'http://localhost:80/',
            'api_path' => 'ajax.php?action=',
            'login_path' => 'login.php',
            'username' => 'prnd',
            'password' => 'bang2Electro',
            'cookie' => '',
            'cookie_expiry' => 0,
            'status' => TRUE
        ],
        "PassThePopcorn" => [
            'type' => 'gazelle',
            'base_url' => 'https://passthepopcorn.me/',
            'api_path' => 'ajax.php?action=',
            'login_path' => 'login.php',
            'username' => 'foo',
            'password' => 'bar',
            'cookie' => '',
            'cookie_expiry' => 0,
            'status' => TRUE
        ],
    ];
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
$this->ExternalServices = $this->ExternalServicesTemplate;
        if (empty($this->ExternalServices)) {
            $this->ExternalServices = G::$Cache->get_value('referral_services');
            // grab from template if not in cache
            if (empty($this->ExternalServices)) {
                $this->ExternalServices = $this->ExternalServicesTemplate;
                $this->cache_services();
            }
        }

        // use php session for lack of better solution
        session_start();

        // init curl object
        if (!function_exists('curl_version')) {
            die('cURL is unavailable on this server.');
        }
        $this->set_curl();

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
     * Verify Method, verifies via API calls that a user has an account at the external service based on the service type.
     *
     * @param $service
     * @param $username
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
     * Login Method, points to relevant login method based on service type
     *
     * @param $service
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
     * @param $service
     * @return bool
     */
    private function gazelle_login($service) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        //check if cookie is still valid
        if (time() < $this->ExternalServices[$service]['cookie_expiry']) {
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
            $this->ExternalServices[$service]['cookie'] = $cookies['session'];
            $this->ExternalServices[$service]['cookie_expiry'] = time() + $this->CookieExpiry;
            $this->cache_services();
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
        curl_setopt($ch, CURLOPT_COOKIE, 'session=' . urlencode($this->ExternalServices[$service]['cookie']));
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
        $user_results = $result['results'];
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

    private function gazelle_verify_token($service, $user_id) {
        if (!array_key_exists($service, $this->ExternalServices)) {
            die("Invalid referral service");
        }
        // login to ensure that we haven't expired out session with the external service
        $this->login($service);
        // build usersearch url
        $url = $this->ExternalServices[$service]['base_url'];
        $url .= $this->ExternalServices[$service]['api_path'];
        $url .= 'user';
        $url .= '&id=' . $user_id;
        // do la requesta
        $ch = curl_init($url);
        $this->set_curl($ch);
        curl_setopt($ch, CURLOPT_COOKIE, 'session=' . urlencode($this->ExternalServices[$service]['cookie']));
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