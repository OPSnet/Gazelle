<?php
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Gazelle\DB;

//-----------------------------------------------------------------------------------
/////////////////////////////////////////////////////////////////////////////////////
/*//-- MySQL wrapper class ----------------------------------------------------------

This class provides an interface to mysqli. You should always use this class instead
of the mysql/mysqli functions, because this class provides debugging features and a
bunch of other cool stuff.

Everything returned by this class is automatically escaped for output. This can be
turned off by setting $escape to false in next_record or to_array.

//--------- Basic usage -------------------------------------------------------------

* Making a query
$db = Gazelle\DB::DB();

$db->prepare_query("
    SELECT *
    FROM table...");

    Is functionally equivalent to using mysqli_query("SELECT * FROM table...")
    Stores the result set in $this->QueryID
    Returns the result set, so you can save it for later (see set_query_id())

* Getting data from a query

$array = $db->next_record();
    Is functionally equivalent to using mysqli_fetch_array($ResultSet)
    You do not need to specify a result set - it uses $this-QueryID

//--------- Advanced usage ---------------------------------------------------------

* The conventional way of retrieving a row from a result set is as follows:

[$All, $Columns, $That, $You, $Select[ = $db->next_record();
-----

* This is how you loop over the result set:

while ([$All, $Columns, $That, $You, $Select] = $db->next_record()) {
    echo "Do stuff with $All of the ".$Columns.$That.$You.$Select;
}
-----

* There are also a couple more mysqli functions that have been wrapped. They are:

record_count()
    Wrapper to mysqli_num_rows()

affected_rows()
    Wrapper to mysqli_affected_rows()

inserted_id()
    Wrapper to mysqli_insert_id()

close
    Wrapper to mysqli_close()
-----

* And, of course, a few handy custom functions.

to_array($Key = false)
    Transforms an entire result set into an array (useful in situations where you
    can't order the rows properly in the query).

    If $Key is set, the function uses $Key as the index (good for looking up a
    field). Otherwise, it uses an iterator.

    For an example of this function in action, check out forum.php.

collect($Key)
    Loops over the result set, creating an array from one of the fields ($Key).
    For an example, see forum.php.

set_query_id($ResultSet)
    This class can only hold one result set at a time. Using set_query_id allows
    you to set the result set that the class is using to the result set in
    $ResultSet. This result set should have been obtained earlier by using
    $db->prepared_query().

    Example:

    $FoodRS = $db->prepared_query("
            SELECT *
            FROM food");
    $db->prepared_query("
        SELECT *
        FROM drink");
    $Drinks = $db->next_record();
    $db->set_query_id($FoodRS);
    $Food = $db->next_record();

    Of course, this example is contrived, but you get the point.

-------------------------------------------------------------------------------------
*///---------------------------------------------------------------------------------

class Mysql {
    public \mysqli|false $LinkID = false;
    protected \mysqli_result|false|null $QueryID = false;
    protected array|false|null $Record = [];
    protected int $Row;
    protected int $Errno = 0;
    protected string $Error = '';
    protected bool $queryLog = true;

    protected string $PreparedQuery;
    protected \mysqli_stmt|false $Statement;

    protected static array $queryList = [];

    public function __construct(
        protected readonly string $Database,
        protected readonly string $User,
        #[\SensitiveParameter] protected readonly string $Pass,
        protected readonly string $Server,
        protected readonly int $Port,
        protected readonly string|null $Socket,
        protected float $elapsed = 0.0,
    ) {}

    public function disableQueryLog(): void {
        $this->queryLog = false;
    }

    public function enableQueryLog(): void {
        $this->queryLog = true;
    }

    public function queryList(): array {
        return static::$queryList;
    }

    public function elapsed(): float {
        return $this->elapsed;
    }

    private function halt(string $Msg): void {
        if ($this->Errno == 1062) {
            throw new MysqlDuplicateKeyException();
        }
        global $Debug;
        $Debug->saveCase("MySQL: error({$this->Errno}) {$this->Error} query=[$this->PreparedQuery]");
        throw new MysqlException("$Msg  -- {$this->Error}");
    }

    public function connect(): void {
        if ($this->LinkID === false) {
            $this->LinkID = mysqli_connect($this->Server, $this->User, $this->Pass, $this->Database, $this->Port, $this->Socket);
            if ($this->LinkID === false) {
                $this->Errno = mysqli_connect_errno();
                $this->Error = mysqli_connect_error();
                $this->halt('Connection failed (host:' . $this->Server . ':' . $this->Port . ')');
            }
        }
    }

    private function setup_query(): void {
        /*
         * If there was a previous query, we store the warnings. We cannot do
         * this immediately after mysqli_query because mysqli_insert_id will
         * break otherwise due to mysqli_get_warnings sending a SHOW WARNINGS;
         * query. When sending a query, however, we're sure that we won't call
         * mysqli_insert_id (or any similar function, for that matter) later on,
         * so we can safely get the warnings without breaking things.
         * Note that this means that we have to call $this->warnings manually
         * for the last query!
         */
        $this->loadPreviousWarning();
        $this->connect();
    }

    /**
     * Prepares an SQL statement for execution with data.
     *
     * Normally, you'll most likely just want to be using
     * Mysql::prepared_query to call both Mysql::prepare()
     * and Mysql::execute() for one-off queries, you can use
     * this separately in the case where you plan to be running
     * this query repeatedly while just changing the bound
     * parameters (such as if doing a bulk update or the like).
     */
    public function prepare(string $Query): \mysqli_stmt|false {
        $this->setup_query();
        $Query = trim($Query);
        $this->PreparedQuery = $Query;
        if ($this->LinkID === false) {
            return false;
        }
        $this->Statement = $this->LinkID->prepare($Query);
        if ($this->Statement === false) {
            $this->Errno = $this->LinkID->errno;
            $this->Error = $this->LinkID->error;
            static::$queryList[] = [
                'query'   => "$Query /* ERROR: {$this->Error} */",
                'elapsed' => 0,
            ];
            $this->halt(sprintf("Invalid Query: %s(%d) [%s]", $this->Error, $this->Errno, $Query));
        }
        return $this->Statement;
    }

    /**
     * Bind variables to our last prepared query and execute it.
     *
     * Variables that are passed into the function will have their
     * type automatically set for how to bind it to the query (either
     * integer (i), double (d), or string (s)).
     */
    public function execute(mixed ...$Parameters): \mysqli_result|false {
        /** @var \mysqli_stmt $Statement */
        $Statement = &$this->Statement;

        if (count($Parameters) > 0) {
            $Binders = "";
            foreach ($Parameters as $Parameter) {
                if (is_int($Parameter)) {
                    $Binders .= "i";
                } elseif (is_double($Parameter)) {
                    $Binders .= "d";
                } else {
                    $Binders .= "s";
                }
            }
            $Statement->bind_param($Binders, ...$Parameters);
        }

        $Closure = function () use ($Statement): \mysqli_result|false {
            try {
                $Statement->execute();
                return $Statement->get_result();
            } catch (\mysqli_sql_exception) {
                if ($this->LinkID && mysqli_error($this->LinkID) == 1062) {
                    throw new MysqlDuplicateKeyException();
                }
            }
            return false;
        };

        $Query = $this->PreparedQuery . ' -- ' . json_encode($Parameters);
        return $this->attempt_query($Query, $Closure);
    }

    /**
     * Prepare and execute a prepared query returning the result set.
     *
     * Utility function that wraps Mysql::prepare() and Mysql::execute()
     * as most times, the query is going to be one-off and this will save
     * on keystrokes. If you do plan to be executing a prepared query
     * multiple times with different bound parameters, you'll want to call
     * the two functions separately instead of this function.
     */
    public function prepared_query(string $Query, mixed ...$Parameters): \mysqli_result|false {
        $this->prepare($Query);
        return $this->execute(...$Parameters);
    }

    /**
     * @param callable(): (\mysqli_result|false) $Closure
     */
    private function attempt_query(string $Query, callable $Closure): \mysqli_result|false {
        $startTime = microtime(true);
        if ($this->LinkID === false) {
            return false;
        }
        // In the event of a MySQL deadlock, we sleep allowing MySQL time to unlock
        // then attempt again for a maximum of 5 attempts
        $sleep = 0.5;
        for ($i = 1; $i < 6; $i++) {
            $this->QueryID = $Closure();
            if (!in_array(mysqli_errno($this->LinkID), [1213, 1205])) {
                break;
            }
            // if we have a viewer, we have a request context, otherwise, it must be script
            global $Debug, $Viewer;
            $Debug->analysis(
                is_null($Viewer) && isset($_SERVER['argv'])
                    ? ($_SERVER['argv'][0] ?? 'cli')
                    : $Viewer->requestContext()->module(),
                'Non-Fatal Deadlock:',
                $Query,
                86_400,
            );
            trigger_error("Database deadlock, attempt $i");

            usleep((int)($sleep * 1e6));
            $sleep *= 1.75;
        }
        $elapsed = (microtime(true) - $startTime) * 1000;
        // Kills admin pages, and prevents Debug->analysis when the whole set exceeds 1 MB
        if (($Len = strlen($Query)) > 16384) {
            $Query = substr($Query, 0, 16384) . '... ' . ($Len - 16384) . ' bytes trimmed';
        }
        if ($this->queryLog) {
            static::$queryList[] = [
                'query'   => $Query,
                'elapsed' => $elapsed,
            ];
        }
        $this->elapsed += $elapsed;

        // Update/Insert/etc statements for prepared queries don't return a QueryID,
        // but mysqli_errno is also going to be 0 for no error
        $this->Errno = mysqli_errno($this->LinkID);
        if (!$this->QueryID && $this->Errno !== 0) {
            $this->Error = mysqli_error($this->LinkID);
            $this->halt("Invalid Query: $Query");
        }

        $this->Row = 0;
        return $this->QueryID;
    }

    public function inserted_id(): ?int {
        return $this->LinkID !== false ? (int)mysqli_insert_id($this->LinkID) : null;
    }

    public function next_row(int $type = MYSQLI_NUM): array|null|false {
        return $this->QueryID ? mysqli_fetch_array($this->QueryID, $type) : false;
    }

    public function next_record(int $Type = MYSQLI_BOTH, array|bool $escape = true): ?array {
        // $escape can be true, false, or an array of keys to not escape
        // If $Reverse is true, then $escape is an array of keys to escape
        if ($this->QueryID) {
            $this->Record = mysqli_fetch_array($this->QueryID, $Type);
            $this->Row++;
            if (!is_array($this->Record)) {
                $this->QueryID = false;
                $this->Record = null;
            } elseif ($escape !== false) {
                $this->Record = $this->display_array($this->Record, $escape);
            }
            return $this->Record;
        }
        return null;
    }

    /**
     * Fetches next record from the result set of the previously executed query.
     *
     * Utility around next_record where we just return the array as MYSQLI_BOTH
     * and require the user to explicitly define which columns to define (as opposed
     * to all columns always being escaped, which is a bad sort of lazy). Things that
     * need to be escaped are strings that users input (with any characters) and
     * are not displayed inside a textarea or input field.
     *
     * @param mixed  $escape Boolean true/false for escaping entire/none of query
     *                          or can be an array of array keys for what columns to escape
     */
    public function fetch_record(mixed ...$escape): ?array {
        if (count($escape) === 1 && $escape[0] === true) {
            $escape = true;
        } elseif (count($escape) === 0) {
            $escape = false;
        }
        return $this->next_record(MYSQLI_BOTH, $escape);
    }

    public function close(): void {
        if ($this->LinkID !== false) {
            $this->halt('Cannot close connection or connection did not open.');
            $this->LinkID = false;
        }
    }

    /*
     * returns an integer with the number of rows found
     * returns a string if the number of rows found exceeds MAXINT
     */
    public function record_count(): int|string|null {
        if ($this->QueryID) {
            return mysqli_num_rows($this->QueryID);
        }
        return null;
    }

    /*
     * returns true if the query exists and there were records found
     * returns false if the query does not exist or if there were 0 records returned
     */
    public function has_results(): bool {
        return ($this->QueryID && $this->record_count() !== 0);
    }

    public function affected_rows(): int {
        if ($this->LinkID) {
            return (int)$this->LinkID->affected_rows;
        }
        /* why the fuck is this necessary for \Gazelle\Bonus\purchaseInvite() ?! */
        if ($this->Statement) {
            return (int)$this->Statement->affected_rows;
        }
        return 0;
    }

    public function info(): string {
        return $this->LinkID ? mysqli_get_host_info($this->LinkID) : '';
    }

    // Creates an array from a result set
    // If $Key is set, use the $Key column in the result set as the array key
    // Otherwise, use an integer
    public function to_array(bool|string $Key = false, int $Type = MYSQLI_BOTH, array|bool $escape = true): array {
        $Return = [];
        if ($this->QueryID) {
            while ($Row = mysqli_fetch_array($this->QueryID, $Type)) {
                if ($escape !== false) {
                    $Row = $this->display_array($Row, $escape);
                }
                if ($Key !== false) {
                    $Return[$Row[$Key]] = $Row;
                } else {
                    $Return[] = $Row;
                }
            }
            mysqli_data_seek($this->QueryID, 0);
        }
        return $Return;
    }

    //  Loops through the result set, collecting the $ValField column into an array with $KeyField as keys
    public function to_pair(string $KeyField, string $ValField, bool $escape = true): array {
        $Return = [];
        if ($this->QueryID) {
            while ($Row = mysqli_fetch_array($this->QueryID)) {
                if ($escape) {
                    $Key = display_str($Row[$KeyField]);
                    $Val = display_str($Row[$ValField]);
                } else {
                    $Key = $Row[$KeyField];
                    $Val = $Row[$ValField];
                }
                $Return[$Key] = $Val;
            }
            mysqli_data_seek($this->QueryID, 0);
        }
        return $Return;
    }

    //  Loops through the result set, collecting the $Key column into an array
    public function collect(int|string $key, bool $escape = true): array {
        $collect = [];
        if ($this->QueryID) {
            while ($row = mysqli_fetch_array($this->QueryID)) {
                $collect[] = $escape ? display_str($row[$key]) : $row[$key];
            }
            mysqli_data_seek($this->QueryID, 0);
        }
        return $collect;
    }

    /**
     * Runs a prepared_query using placeholders and returns the matched row.
     * Stashes the current query id so that this can be used within a block
     * that is looping over an active resultset.
     */
    public function row(string $sql, mixed ...$args): ?array {
        $qid = $this->get_query_id();
        $this->prepared_query($sql, ...$args);
        $result = $this->next_record(MYSQLI_NUM, false);
        $this->set_query_id($qid);
        return $result;
    }

    /**
     * Runs a prepared_query using placeholders and returns the matched row.
     * Stashes the current query id so that this can be used within a block
     * that is looping over an active resultset.
     *
     * @param mixed   $args  The values of the placeholders
     * @return array  key=>value resultset or null
     */
    public function rowAssoc(string $sql, mixed ...$args): ?array {
        $qid = $this->get_query_id();
        $this->prepared_query($sql, ...$args);
        $result = $this->next_record(MYSQLI_ASSOC, false);
        $this->set_query_id($qid);
        return $result;
    }

    /**
     * Runs a prepared_query using placeholders and returns the first element
     * of the first row.
     * Stashes the current query id so that this can be used within a block
     * that is looping over an active resultset.
     */
    public function scalar(string $sql, mixed ...$args): int|float|string|bool|null {
        $qid = $this->get_query_id();
        $this->prepared_query($sql, ...$args);
        $result = $this->has_results() ? $this->next_record(MYSQLI_NUM, false) : [null];
        $this->set_query_id($qid);
        return $result[0];
    }

    /**
     * Does a table.column exist in the database? This helps when code needs to
     * deal with (legitimate) variations in the schema.
     */
    public function entityExists(string $table, string $column): bool {
        return (bool)$this->scalar("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?
            ", SQLDB, $table, $column
        );
    }

    public function set_query_id(mixed &$ResultSet): void {
        $this->QueryID = $ResultSet;
        $this->Row = 0;
    }

    public function get_query_id(): \mysqli_result|false {
        return $this->QueryID ?? false;
    }

    /**
     * This function determines whether the last query caused warning messages
     * and stores them in the end entry of static::$queryList.
     */
    public function loadPreviousWarning(): int {
        if ($this->LinkID === false) {
            return 0;
        }
        $e = mysqli_get_warnings($this->LinkID);
        $list = [];
        if ($e !== false) {
            do {
                if ($e->errno == 1592) {
                    // 1592: Unsafe statement written to the binary log using statement format since BINLOG_FORMAT = STATEMENT.
                    continue;
                }
                $list[] = [
                    'code'    => $e->errno,
                    'message' => $e->message
                ];
            } while ($e->next());
        }
        if ($list) {
            static::$queryList[count(static::$queryList) - 1]['warning'] = $list;
        }
        return count($list);
    }

    public function begin_transaction(): void {
        if (!$this->LinkID !== false) {
            $this->connect();
        }
        if ($this->LinkID !== false) {
            mysqli_begin_transaction($this->LinkID);
        }
    }

    public function commit(): void {
        if ($this->LinkID) {
            mysqli_commit($this->LinkID);
        }
    }

    public function rollback(): void {
        if ($this->LinkID) {
            mysqli_rollback($this->LinkID);
        }
    }

    public function dropTemporaryTable(string $tableName): void {
        $this->prepared_query("
            DROP TEMPORARY TABLE IF EXISTS $tableName
        ");
    }

    /**
     * HTML escape an entire array for output.
     * @param boolean|array $escape
     *    if true, all keys escaped
     *    if false, no escaping.
     *    If array, it's a list of array keys not to escape.
     */
    protected function display_array(array $field, array|bool $escape): array {
        foreach ($field as $key => $val) {
            if ($escape === true || (is_array($escape) && !in_array($key, $escape))) {
                $field[$key] = display_str($val);
            }
        }
        return $field;
    }
}
