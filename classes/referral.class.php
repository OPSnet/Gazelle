<?php

/**
 * Class Referral
 * @author prnd
 * @created 2017-01-02
 *
 */
class Referral {

    // curl object
    private $curl;
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
            'password' => 'foo',
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
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->curl, CURLOPT_MAXREDIRS, 10);


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

        $_SESSION['referral_token'] = 'APL:' . Users::make_secret(1024) . ':APL';
        return $_SESSION['referral_token'];
    }

    public function verify_token($service, $username) {

        if (!$this->login($service)) {
            die("This referral service is unavailable.");
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
        curl_setopt($this->curl, CURLOPT_URL, $this->ExternalServices[$service]['base_url'] . $this->ExternalServices[$service]['login_path']);
        curl_setopt($this->curl, CURLOPT_POST, TRUE);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($login_fields));
        // do el requesto
        $result = curl_exec($this->curl);
        $header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
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
}