<?php

namespace Gazelle;

use Gazelle\Util\{Irc, Time};

class Debug {
    protected static int $caseCount = 0;
    protected static array $Errors = [];
    protected static array $markList = [];
    protected float $cpuStart;
    protected float $epochStart;
    protected float $timerStart;

    public function __construct(
        protected Cache    $cache,
        protected DB\Mysql $db,
    ) {
        $this->timerStart = hrtime(true);
        $this->epochStart = microtime(true);
        $this->cpuStart   = $this->cpuElapsed();
        set_error_handler($this->errorHandler(...));
    }

    public function flush(): int {
        $cleared = count(self::$Errors);
        self::$Errors = [];
        return $cleared;
    }

    public function epochStart(): float {
        return $this->epochStart;
    }

    public function duration(): float {
        return (hrtime(true) - $this->timerStart) * 1e-9;
    }

    public function profile(User $user, bool $profile): ErrorLog|null {
        $reason = [];

        $errorTotal = count($this->errorList());
        if ($errorTotal > 0) {
            $reason[] = "$errorTotal PHP errors";
        }

        $this->db->loadPreviousWarning(); // see comment in MYSQL::query

        if ($profile) {
            $reason[] = "Requested by {$user->username()}";
        }

        if ($reason !== []) {
            return $this->analysis(
                $user->requestContext()->module(),
                implode(', ', $reason)
            );
        }
        return null;
    }

    public function saveCase(
        string $message,
        Manager\ErrorLog $manager = new Manager\ErrorLog(),
    ): ErrorLog {
        if (!isset($_SERVER['REQUEST_URI'])) {
            $uri    = 'cli';
            $userId = 0;
        } else {
            $uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $uri = preg_replace('/(?<=[?&]auth=)\w+/', 'AUTH', $uri);
            $uri = preg_replace('/(?<=[?&]torrent_pass=)\w+/', 'HASH', $uri);
            $uri = preg_replace('/([?&]\w*id=)\d+/', '\1IDnnn', $uri);
            global $Viewer;
            $userId = (int)$Viewer?->id;
        }
        $errorLog = $manager->create(
           uri:       $uri,
           userId:    $userId,
           duration:  $this->duration(),
           memory:    memory_get_usage(true),
           nrQuery:   count(DB::DB()->queryList()),
           nrCache:   $this->cache->hitListTotal(),
           trace:     $message,
           request:   $_REQUEST,
           errorList: $this->errorList(),
        );
        $this->cache->cache_value(
            "analysis_{$errorLog->id}", [
                'URI' => PHP_SAPI === 'cli'
                    ? 'cli'
                    : "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}",
                'message'       => $message,
                'time'          => time(),
                'errors'        => $this->errorList(true),
                'flags'         => $this->markList(),
                'includes'      => $this->includeList(),
                'perf'          => $this->perfInfo(),
                'ocelot'        => new Tracker()->requestList(),
                'searches'      => class_exists('Sphinxql') ? \Sphinxql::$Queries : [],
                'searches_time' => class_exists('Sphinxql') ? \Sphinxql::$Time : 0.0,
                'queries'       => $this->db->queryList(),
                'queries_time'  => $this->db->elapsed(),
                'cache'         => $this->cache->hitList(),
                'cache_time'    => $this->cache->elapsed(),
            ],
            86400 * 2
        );
        static::$caseCount++;
        return $errorLog;
    }

    public function analysis(string $module, string $message, string $report = ''): ErrorLog|null {
        $uri = empty($_SERVER['REQUEST_URI']) ? '' : substr($_SERVER['REQUEST_URI'], 1);
        if (
            (PHP_SAPI === 'cli' && !str_ends_with($_SERVER["SCRIPT_NAME"], "vendor/bin/phpunit"))
            || in_array($uri, ['tools.php?action=db_sandbox'])
        ) {
            // Don't spam IRC from Boris or these pages
            return null;
        }
        if (empty($report)) {
            $report = $message;
        }
        $case = $this->saveCase($report);
        Irc::sendMessage(IRC_CHAN_STATUS, "{$message} $module "
            . SITE_URL . "/tools.php?action=analysis&case={$case->id} "
            . SITE_URL . "/{$uri}"
        );
        return $case;
    }

    public function saveError(\Error|\Exception $e): ErrorLog {
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

    // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
    // for $Level
    public function errorHandler(int $Level, string $Error, string $File, int $Line): bool {
        $Steps = 1; //Steps to go up in backtrace, default one
        $Call = '';
        $Args = '';
        $Tracer = debug_backtrace();

        // This is in case something in this function goes wrong and we get stuck with an infinite loop
        if (($Tracer[$Steps]['class'] ?? '') === \Gazelle\Debug::class && $Tracer[$Steps]['function'] === 'errorHandler') {
            return true;
        }

        //If this error was thrown, we return the function which threw it
        if ($Tracer[$Steps]['function'] == 'trigger_error') {
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
        if (isset($Tracer[$Steps]) && ($Tracer[$Steps]['function'] == 'include' || $Tracer[$Steps]['function'] == 'require') && isset($Tracer[$Steps]['args'][0]) && $Tracer[$Steps]['args'][0] == $File) {
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

        self::$Errors[] = [
            str_replace(SERVER_ROOT . '/', '', $Error),
            str_replace(SERVER_ROOT . '/', '', $File) . ":$Line",
            $Call,
            $Args
        ];
        return true;
    }

    // phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed

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
            'URI'               => PHP_SAPI === 'cli'
                ? 'cli'
                : "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}",
            'Memory usage'      => byte_format(memory_get_usage(true)),
            'Page process time' => number_format($this->duration(), 3) . ' s',
            'Script start'      => Time::sqlTime($this->epochStart),
            'Script end'        => Time::sqlTime(microtime(true)),
        ];
    }

    /* A list of keys to represent durations (50ms, 100ms, 200ms ... 1000ms and then 1s increments up to 60s) */
    public function durationHistogramKeyList(): array {
        return array_map(
            fn ($t) => sprintf('exec_d_%05d', $t),
            [
                50,
                ...array_map(
                    fn ($t) => $t * 100,
                    range(1, 10),
                ),
                ...array_map(
                    fn ($t) => $t * 1000,
                    range(2, 60),
                )
            ]
        );
    }

    /* A list of keys to represent memory sizes in 1MiB increments */
    public function memoryHistogramKeyList(): array {
        return array_map(
            fn ($t) => sprintf('exec_m_%03d', $t),
            range(1, 128),
        );
    }

    /* initialise the duration histogram */
    public function initDurationHistogram(): bool {
        return $this->cache->setMulti(
            array_fill_keys($this->durationHistogramKeyList(), 0),
            0
        );
    }

    /* initialise the memory histogram */
    public function initMemoryHistogram(): bool {
        return $this->cache->setMulti(
            array_fill_keys($this->memoryHistogramKeyList(), 0),
            0,
        );
    }

    public function durationRound(float $duration): int {
        return match (true) {
            $duration <=  50000 => 50,
            $duration < 1000000 => (int)ceil($duration /   100_000) *  100,
            default             => (int)ceil($duration / 1_000_000) * 1000,
        };
    }

    public function memoryRound(float $size): int {
        return (int)($size / (1024 * 1024));
    }

    /* increment the appropriate bucket for the given duration */
    public function storeDuration(float $duration): int {
        $rounded = $this->durationRound($duration);
        return $this->cache->increment(sprintf('exec_d_%05d', $rounded)) === false
            ? 0
            : $rounded;
    }

    /* increment the appropriate bucket for the given duration */
    public function storeMemory(float $memory): int {
        $rounded = $this->memoryRound($memory);
        return $this->cache->increment(sprintf('exec_m_%03d', $rounded)) === false
            ? 0
            : $rounded;
    }

    /* the duration histogram, an assocative array of msec => count pairs */
    public function durationHistogram(): array {
        $histogram = $this->cache->getMulti($this->durationHistogramKeyList());
        if ($histogram === false) {
            return [];
        }
        $result = [];
        foreach ($histogram as $key => $value) {
            $key = (string)$key;
            $duration = (int)substr($key, (int)strrpos($key, '_') + 1);
            $result[$duration < 1000 ? "{$duration}ms" : ($duration / 1000) . "s"] = $value;
        }
        return $result;
    }

    /* the duration histogram, an assocative array of msec => count pairs */
    public function memoryHistogram(): array {
        $histogram = $this->cache->getMulti($this->memoryHistogramKeyList());
        if ($histogram === false) {
            return [];
        }
        $result = [];
        foreach ($histogram as $key => $value) {
            $key = (string)$key;
            $result[((int)substr($key, (int)strrpos($key, '_') + 1)) . "MiB"] = $value;
        }
        return $result;
    }
}
