<?php

namespace Gazelle;

use \Gazelle\Util\{Irc, Time};

ini_set('max_execution_time',600);

class Debug {
    protected const MAX_TIME = 20000;
    protected const MAX_ERRORS = 0; //Maxmimum errors, warnings, notices we will allow in a page
    protected const MAX_MEMORY = 80 * 1024 * 1024; //Maximum memory used per pageload

    protected static Cache $cache;
    protected static \DB_MYSQL $db;

    protected static int $caseCount = 0;
    protected static array $Errors = [];
    protected static array $Flags = [];
    protected static array $Perf = [];
    protected static array $LoggedVars = [];

    protected static float $startTime;
    protected static $cpuTime = false;

    public function __construct(\Gazelle\Cache $cache, \DB_MYSQL $db) {
        if (self::$cpuTime === false) {
            $r = getrusage();
            self::$cpuTime = $r['ru_utime.tv_sec'] * 1000000 + $r['ru_utime.tv_usec'];
        }
        self::$cache =& $cache;
        self::$db    =& $db;
    }

    public function handle_errors() {
        error_reporting(E_WARNING | E_ERROR | E_PARSE);
        set_error_handler([$this, 'php_error_handler']);
        return $this;
    }

    public function setStartTime(float $startTime) {
        self::$startTime = $startTime;
        return $this;
    }

    public function startTime(): float {
        return self::$startTime;
    }

    public function profile(User $user, string $document, string $Automatic = '') {
        $Reason = [];

        if (!empty($Automatic)) {
            $Reason[] = $Automatic;
        }

        $Micro = (microtime(true) - self::$startTime) * 1000;
        if ($Micro > self::MAX_TIME && !in_array($document, IGNORE_PAGE_MAX_TIME)) {
            $Reason[] = number_format($Micro, 3).' ms';
        }

        $Errors = count($this->get_errors());
        if ($Errors > self::MAX_ERRORS) {
            $Reason[] = $Errors.' PHP errors';
        }
        $Ram = memory_get_usage(true);
        if ($Ram > self::MAX_MEMORY && !in_array($document, IGNORE_PAGE_MAX_MEMORY)) {
            $Reason[] = \Format::get_size($Ram).' RAM used';
        }

        self::$db->warnings(); // see comment in MYSQL::query

        $CacheStatus = self::$cache->server_status();
        if (in_array(0, $CacheStatus) && !self::$cache->get_value('cache_fail_reported')) {
            // Limit to max one report every 15 minutes to avoid massive debug spam
            self::$cache->cache_value('cache_fail_reported', true, 900);
            $Reason[] = "Cache server error";
        }

        if (isset($_REQUEST['profile'])) {
            $Reason[] = 'Requested by ' . $user->username();
        }

        if (isset($Reason[0])) {
            $this->log_var($CacheStatus, 'Cache server status');
            $this->analysis(implode(', ', $Reason));
            return true;
        }

        return false;
    }

    public function saveCase(string $message): int {
        if (self::$caseCount++) {
            return 0;
        }
        $duration = microtime(true) - self::$startTime;
        if (!isset($_SERVER['REQUEST_URI'])) {
            $uri    = 'cli';
            $userId = 0;
        } else {
            $uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $uri = preg_replace('/(?<=[?&]auth=)\w+/', 'AUTH', $uri);
            $uri = preg_replace('/(?<=[?&]torrent_pass=)\w+/', 'HASH', $uri);
            $uri = preg_replace('/([?&]\w*id=)\d+/', '\1IDnnn', $uri);
            global $Viewer;
            if (isset($Viewer)) {
                $userId = $Viewer->id();
            }
        }

        $id = (new \Gazelle\Manager\ErrorLog)->create(
           uri:       $uri,
           userId:    $userId,
           duration:  $duration,
           memory:    memory_get_usage(true),
           nrQuery:   count($this->get_queries()),
           nrCache:   count($this->get_cache_keys()),
           digest:    md5($message, true),
           trace:     $message,
           request:   json_encode($_REQUEST),
           errorList: json_encode(self::$Errors),
           loggedVar: json_encode(self::$LoggedVars),
        );

        self::$cache->cache_value(
            'analysis_'.$id, [
                'URI'      => isset($_SERVER['REQUEST_URI']) ? ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) : 'cli',
                'message'  => $message,
                'time'     => time(),
                'errors'   => $this->get_errors(true),
                'flags'    => $this->get_flags(),
                'includes' => $this->get_includes(),
                'vars'     => $this->get_logged_vars(),
                'perf'     => $this->get_perf(),
                'ocelot'        => (new \Gazelle\Tracker)->requestList(),
                'searches'      => class_exists('Sphinxql') ? \Sphinxql::$Queries : [],
                'searches_time' => class_exists('Sphinxql') ? \Sphinxql::$Time : 0.0,
                'queries'       => $this->get_queries(),
                'queries_time'  => self::$db->Time,
                'cache'         => $this->get_cache_keys(),
                'cache_time'    => self::$cache->Time,
            ],
            86400 * 2
        );

        return $id;
    }

    public function analysis($Message, $Report = '', $Time = 43200) {
        $RequestURI = empty($_SERVER['REQUEST_URI']) ? '' : substr($_SERVER['REQUEST_URI'], 1);
        if (PHP_SAPI === 'cli'
            || in_array($RequestURI, ['tools.php?action=db_sandbox'])
        ) {
            // Don't spam IRC from Boris or these pages
            return;
        }
        if (empty($Report)) {
            $Report = $Message;
        }
        $case = $this->saveCase($Report);
        global $Document;
        Irc::sendRaw('PRIVMSG '.LAB_CHAN." :{$Message} $Document "
            . SITE_URL."/tools.php?action=analysis&case=$case "
            . SITE_URL.'/'.$RequestURI
        );
    }

    public function saveError(\Exception $e) {
        $this->saveCase(
            $e->getMessage() . "\n"
            . str_replace(SERVER_ROOT .'/', '', $e->getTraceAsString())
        );
    }

    public function get_cpu_time() {
        if (!defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $RUsage = getrusage();
            self::$cpuTime = $RUsage['ru_utime.tv_sec'] * 1000000 + $RUsage['ru_utime.tv_usec'] - self::$cpuTime;
            return self::$cpuTime;
        }
        return false;
    }

    public function log_var($Var, $VarName = false) {
        $BackTrace = debug_backtrace();
        $ID = randomString(5);
        self::$LoggedVars[$ID] = [
            'name' => $VarName ?: $ID,
            'path' => substr($BackTrace[0]['file'], strlen(SERVER_ROOT) + 1),
            'line' => $BackTrace[0]['line'],
            'data' => json_encode($Var, JSON_PRETTY_PRINT),
        ];
    }

    public function set_flag($Event) {
        self::$Flags[] = [$Event, (microtime(true) - self::$startTime) * 1000, memory_get_usage(true), $this->get_cpu_time()];
        return $this;
    }

    protected function format_args($Array) {
        $LastKey = -1;
        $Return = [];
        foreach ($Array as $Key => $Val) {
            $Return[$Key] = '';
            if (!is_numeric($Key) || !is_numeric($LastKey) || $Key != $LastKey + 1) {
                $Return[$Key] .= "'$Key' => ";
            }
            if ($Val === true) {
                $Return[$Key] .= 'true';
            } elseif ($Val === false) {
                $Return[$Key] .= 'false';
            } elseif (is_numeric($Val)) {
                $Return[$Key] .= $Val;
            } elseif (is_string($Val)) {
                $Return[$Key] .= "'$Val'";
            } elseif (is_object($Val)) {
                $Return[$Key] .= get_class($Val);
            } elseif (is_array($Val)) {
                $Return[$Key] .= '['.$this->format_args($Val).']';
            }
            $LastKey = $Key;
        }
        return implode(', ', $Return);
    }

    public function php_error_handler($Level, $Error, $File, $Line) {
        //Who added this, it's still something to pay attention to...
        if (stripos('Undefined index', $Error) !== false) {
            //return true;
        }

        $Steps = 1; //Steps to go up in backtrace, default one
        $Call = '';
        $Args = '';
        $Tracer = debug_backtrace();

        //This is in case something in this function goes wrong and we get stuck with an infinite loop
        if (isset($Tracer[$Steps]['function'], $Tracer[$Steps]['class']) && $Tracer[$Steps]['function'] == 'php_error_handler' && $Tracer[$Steps]['class'] == 'DEBUG') {
            return true;
        }

        //If this error was thrown, we return the function which threw it
        if (isset($Tracer[$Steps]['function']) && $Tracer[$Steps]['function'] == 'trigger_error') {
            $Steps++;
            $File = $Tracer[$Steps]['file'];
            $Line = $Tracer[$Steps]['line'];
        }

        //At this time ONLY Array strict typing is fully supported.
        //Allow us to abuse strict typing (IE: function test(Array))
        if (preg_match('/^Argument (\d+) passed to \S+ must be an (array), (array|string|integer|double|object) given, called in (\S+) on line (\d+) and defined$/', $Error, $Matches)) {
            $Error = 'Type hinting failed on arg '.$Matches[1]. ', expected '.$Matches[2].' but found '.$Matches[3];
            $File = $Matches[4];
            $Line = $Matches[5];
        }

        //Lets not be repetitive
        if (($Tracer[$Steps]['function'] == 'include' || $Tracer[$Steps]['function'] == 'require' ) && isset($Tracer[$Steps]['args'][0]) && $Tracer[$Steps]['args'][0] == $File) {
            unset($Tracer[$Steps]['args']);
        }

        //Class
        if (isset($Tracer[$Steps]['class'])) {
            $Call .= $Tracer[$Steps]['class'].'::';
        }

        //Function & args
        if (isset($Tracer[$Steps]['function'])) {
            $Call .= $Tracer[$Steps]['function'];
            if (isset($Tracer[$Steps]['args'][0])) {
                $Args = $this->format_args($Tracer[$Steps]['args']);
            }
        }

        //Shorten the path & we're done
        $File = str_replace(SERVER_ROOT . '/', '', $File);
        $Error = str_replace(SERVER_ROOT . '/', '', $Error);

        if (DEBUG_WARNINGS) {
            self::$Errors[] = [$Error, $File.':'.$Line, $Call, $Args];
        }
        return true;
    }

    /* Data wrappers */

    public function get_cache_keys() {
        $list = [];
        $keys = array_keys(self::$cache->hits());
        foreach ($keys as $key) {
            $list[$key] = print_r(self::$cache->get_value($key, true), true);
        }
        ksort($list, SORT_NATURAL);
        return $list;
    }

    public function get_classes() {
        $Classes = [];
        foreach (get_declared_classes() as $class) {
            if (!preg_match('/^Gazelle/', $class)) {
                continue;
            }
            $Classes[$class] = [
                'vars' => get_class_vars($class),
                'methods' => get_class_methods($class),
            ];
        }
        return $Classes;
    }

    public function get_errors($Light = false) {
        //Because the cache can't take some of these variables
        if ($Light) {
            foreach (self::$Errors as $Key => $Value) {
                self::$Errors[$Key][3] = '';
            }
        }
        return self::$Errors;
    }

    public function get_extensions() {
        $Extensions = [];
        foreach (get_loaded_extensions() as $Extension) {
            $Extensions[$Extension] = get_extension_funcs($Extension);
            if ($Extensions[$Extension] !== false) {
                sort($Extensions[$Extension]);
            }
        }
        return $Extensions;
    }

    public function get_flags() {
        return self::$Flags;
    }

    public function get_includes() {
        return array_map(fn($inc) => str_replace(SERVER_ROOT . '/', '', $inc), get_included_files());
    }

    public function get_logged_vars() {
        return self::$LoggedVars;
    }

    public function get_perf() {
        if (empty(self::$Perf)) {
            $PageTime = (microtime(true) - self::$startTime);
            $CPUTime = $this->get_cpu_time();
            $Perf = [
                'URI' => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                'Memory usage' => \Format::get_size(memory_get_usage(true)),
                'Page process time' => number_format($PageTime, 3).' s',
            ];
            if ($CPUTime) {
                $Perf['CPU time'] = number_format($CPUTime / 1000000, 3).' s';
            }
            $Perf['Script start'] = Time::sqlTime(self::$startTime);
            $Perf['Script end'] = Time::sqlTime(microtime(true));
            return $Perf;
        }
        return self::$Perf;
    }

    public function get_queries() {
        $list = [];
        foreach (self::$db->Queries as $q) {
            $q[0] = preg_replace('/\s+/', ' ', trim($q[0]));
            $list[] = $q;
        }
        return $list;
    }

    public function get_queries_br() {
        $list = [];
        foreach (self::$db->Queries as $q) {
            $q[0] = preg_replace('/\s+/', ' ', nl2br(trim($q[0])));
            $list[] = $q;
        }
        return $list;
    }
}
