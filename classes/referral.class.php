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
            'username' => 'foo',
            'password' => 'bar',
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


    /**
     * Constructor
     *
     *
     * @author prnd
     * @created 2017-01-02
     */
    function __construct() {
        // populate services array from cache if it exists, if not then grab from template
        if (empty($this->ExternalServices)) {
            $this->ExternalServices = G::$Cache->get_value('referral_services');
            // grab from template if not in cache
            if (empty($this->ExternalServices)) {
                $this->ExternalServices = $this->ExternalServicesTemplate;
                $this->cache_services();
            }
        }


        // init curl object
        if (!function_exists('curl_version')) {
            die('cURL is unavailable on this server.');
        }
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);


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
            $response[] = $key;
        }
        return $response;
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
        // do the request
        curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $code == 200;
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
        curl_setopt($this->curl, CURLOPT_HEADER, 1);
        curl_setopt($this->curl, CURLOPT_POST, TRUE);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($login_fields));
        // do el requesto
        $result = curl_exec($this->curl);
        //check for session cookie, as it is the indicator of success
        $cookies = $this->parse_cookies($result);
        if (array_key_exists('session', $cookies)) {
            $this->ExternalServices[$service]['cookie'] = $cookies['session'];
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