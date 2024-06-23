<?php

namespace Gazelle;

use Gazelle\Util\{Irc, Time};

class Debug {
    protected const MAX_TIME = 20000;
    protected const MAX_ERRORS = 0; //Maxmimum errors, warnings, notices we will allow in a page
    protected const MAX_MEMORY = 80 * 1024 * 1024; //Maximum memory used per pageload

    protected static int $caseCount = 0;
    protected static array $Errors = [];
    protected static array $markList = [];
    protected float $cpuStart;
    protected float $epochStart;

    public function __construct(
        protected Cache    $cache,
        protected DB\Mysql $db,
    ) {
        $this->epochStart = microtime(true);
        $this->cpuStart = $this->cpuElapsed();
        error_reporting(E_WARNING | E_ERROR | E_PARSE);
        set_error_handler($this->errorHandler(...));
    }

    public function epochStart(): float {
        return $this->epochStart;
    }

    public function duration(): float {
        return microtime(true) - $this->epochStart();
    }

    public function profile(User $user, string $document, string $Automatic = ''): bool {
        $Reason = [];

        if (!empty($Automatic)) {
            $Reason[] = $Automatic;
        }

        $Micro = $this->duration() * 1000;
        if ($Micro > self::MAX_TIME && !in_array($document, IGNORE_PAGE_MAX_TIME)) {
            $Reason[] = number_format($Micro, 3) . ' ms';
        }

        $Errors = count($this->errorList());
        if ($Errors > self::MAX_ERRORS) {
            $Reason[] = $Errors . ' PHP errors';
        }
        $Ram = memory_get_usage(true);
        if ($Ram > self::MAX_MEMORY && !in_array($document, IGNORE_PAGE_MAX_MEMORY)) {
            $Reason[] = byte_format($Ram) . ' RAM used';
        }

        $this->db->loadPreviousWarning(); // see comment in MYSQL::query

        if (isset($_REQUEST['profile'])) {
            $Reason[] = 'Requested by ' . $user->username();
        }

        if (isset($Reason[0])) {
            $this->analysis(
                $user->requestContext()->module(),
                implode(', ', $Reason)
            );
            return true;
        }

        return false;
    }

    public function saveCase(string $message): int {
        if (static::$caseCount++) {
            return 0;
        }
        if (!isset($_SERVER['REQUEST_URI'])) {
            $uri    = 'cli';
            $userId = 0;
        } else {
            $uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $uri = preg_replace('/(?<=[?&]auth=)\w+/', 'AUTH', $uri);
            $uri = preg_replace('/(?<=[?&]torrent_pass=)\w+/', 'HASH', $uri);
            $uri = preg_replace('/([?&]\w*id=)\d+/', '\1IDnnn', $uri);
            global $Viewer;
            $userId = (int)$Viewer?->id();
        }

        $errorList = (string)json_encode(self::$Errors);
        $id = (new \Gazelle\Manager\ErrorLog())->create(
           uri:       $uri,
           userId:    $userId,
           duration:  $this->duration(),
           memory:    memory_get_usage(true),
           nrQuery:   count(DB::DB()->queryList()),
           nrCache:   $this->cache->hitListTotal(),
           digest:    hash('xxh3', $message . $errorList, true),
           trace:     $message,
           request:   (string)json_encode($_REQUEST),
           errorList: $errorList,
           loggedVar: (string)json_encode(''),
        );

        $this->cache->cache_value(
            'analysis_' . $id, [
                'URI' => isset($_SERVER['REQUEST_URI'])
                    ? "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"
                    : 'cli',
                'message'       => $message,
                'time'          => time(),
                'errors'        => $this->errorList(true),
                'flags'         => $this->markList(),
                'includes'      => $this->includeList(),
                'perf'          => $this->perfInfo(),
                'ocelot'        => (new \Gazelle\Tracker())->requestList(),
                'searches'      => class_exists('Sphinxql') ? \Sphinxql::$Queries : [],
                'searches_time' => class_exists('Sphinxql') ? \Sphinxql::$Time : 0.0,
                'queries'       => $this->db->queryList(),
                'queries_time'  => $this->db->elapsed(),
                'cache'         => $this->cache->hitList(),
                'cache_time'    => $this->cache->elapsed(),
            ],
            86400 * 2
        );

        return $id;
    }

    public function analysis(string $module, string $message, string $report = ''): void {
        $uri = empty($_SERVER['REQUEST_URI']) ? '' : substr($_SERVER['REQUEST_URI'], 1);
        if (
            PHP_SAPI === 'cli'
            || in_array($uri, ['tools.php?action=db_sandbox'])
        ) {
            // Don't spam IRC from Boris or these pages
            return;
        }
        if (empty($report)) {
            $report = $message;
        }
        $case = $this->saveCase($report);
        Irc::sendMessage(IRC_CHAN_STATUS, "{$message} $module "
            . SITE_URL . "/tools.php?action=analysis&case=$case "
            . SITE_URL . "/{$uri}"
        );
    }

    public function saveError(\Exception $e): int {
        return $this->saveCase(
            $e->getMessage() . "\n"
            . str_replace(SERVER_ROOT . '/', '', $e->getTraceAsString())
        );
    }

    public function cpuElapsed(): float {
        if (!defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $r = getrusage();
            if ($r) {
                return $r['ru_utime.tv_sec'] * 1_000_000 + $r['ru_utime.tv_usec'];
            }
        }
        return 0.0;
    }

    public function mark(string $event): static {
        self::$markList[] = [
            $event,
            $this->duration() * 1000,
            memory_get_usage(true),
            $this->cpuElapsed() - $this->cpuStart,
        ];
        return $this;
    }

    protected function format(array $Array): string {
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
                $Return[$Key] .= $Val::class;
            } elseif (is_array($Val)) {
                $Return[$Key] .= '[' . $this->format($Val) . ']';
            }
            $LastKey = $Key;
        }
        return implode(', ', $Return);
    }

    public function errorHandler(int $Level, string $Error, string $File, int $Line): bool {
        $Steps = 1; //Steps to go up in backtrace, default one
        $Call = '';
        $Args = '';
        $Tracer = debug_backtrace();

        // This is in case something in this function goes wrong and we get stuck with an infinite loop
        if (isset($Tracer[$Steps]['function'], $Tracer[$Steps]['class']) && $Tracer[$Steps]['function'] == 'errorHandler' && $Tracer[$Steps]['class'] == 'DEBUG') { /** @phpstan-ignore-line */
            return true;
        }

        //If this error was thrown, we return the function which threw it
        if (isset($Tracer[$Steps]['function']) && $Tracer[$Steps]['function'] == 'trigger_error') {
            $Steps++;
            $File = $Tracer[$Steps]['file']; /** @phpstan-ignore-line */
            $Line = $Tracer[$Steps]['line']; /** @phpstan-ignore-line */
        }

        //At this time ONLY Array strict typing is fully supported.
        //Allow us to abuse strict typing (IE: function test(Array))
        if (preg_match('/^Argument (\d+) passed to \S+ must be an (array), (array|string|integer|double|object) given, called in (\S+) on line (\d+) and defined$/', $Error, $Matches)) {
            $Error = 'Type hinting failed on arg ' . $Matches[1] . ', expected ' . $Matches[2] . ' but found ' . $Matches[3];
            $File = $Matches[4];
            $Line = $Matches[5];
        }

        //Lets not be repetitive
        if (isset($Tracer[$Steps]) && ($Tracer[$Steps]['function'] == 'include' || $Tracer[$Steps]['function'] == 'require' ) && isset($Tracer[$Steps]['args'][0]) && $Tracer[$Steps]['args'][0] == $File) {
            unset($Tracer[$Steps]['args']);
        }

        //Class
        if (isset($Tracer[$Steps]['class'])) {
            $Call .= $Tracer[$Steps]['class'] . '::';
        }

        //Function & args
        if (isset($Tracer[$Steps]['function'])) {
            $Call .= $Tracer[$Steps]['function'];
            if (isset($Tracer[$Steps]['args'][0])) {
                $Args = $this->format($Tracer[$Steps]['args']);
            }
        }

        if (DEBUG_WARNINGS) {
            self::$Errors[] = [
                str_replace(SERVER_ROOT . '/', '', $Error),
                str_replace(SERVER_ROOT . '/', '', $File) . ":$Line",
                $Call,
                $Args
            ];
        }
        return true;
    }

    /* Data wrappers */

    public function errorList($Light = false): array {
        //Because the cache can't take some of these variables
        if ($Light) {
            foreach (array_keys(self::$Errors) as $Key) {
                self::$Errors[$Key][3] = '';
            }
        }
        return self::$Errors;
    }

    public function markList(): array {
        return self::$markList;
    }

    public function includeList(): array {
        return array_map(
            fn($inc) => str_replace(SERVER_ROOT . '/', '', $inc),
            get_included_files()
        );
    }

    public function perfInfo(): array {
        return [
            'CPU time'          => number_format(($this->cpuElapsed() - $this->cpuStart) / 1_000_000, 3) . ' s',
            'URI'               => "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}",
            'Memory usage'      => byte_format(memory_get_usage(true)),
            'Page process time' => number_format($this->duration(), 3) . ' s',
            'Script start'      => Time::sqlTime($this->epochStart()),
            'Script end'        => Time::sqlTime(microtime(true)),
        ];
    }
}
