<?php
//-----------------------------------------------------------------------------------
/////////////////////////////////////////////////////////////////////////////////////
/*//-- MySQL wrapper class ----------------------------------------------------------

This class provides an interface to mysqli. You should always use this class instead
of the mysql/mysqli functions, because this class provides debugging features and a
bunch of other cool stuff.

Everything returned by this class is automatically escaped for output. This can be
turned off by setting $Escape to false in next_record or to_array.

//--------- Basic usage -------------------------------------------------------------

* Creating the object.

require(SERVER_ROOT.'/classes/mysql.class.php');
$DB = new DB_MYSQL;
-----

* Making a query

$DB->query("
    SELECT *
    FROM table...");

    Is functionally equivalent to using mysqli_query("SELECT * FROM table...")
    Stores the result set in $this->QueryID
    Returns the result set, so you can save it for later (see set_query_id())
-----

* Getting data from a query

$array = $DB->next_record();
    Is functionally equivalent to using mysqli_fetch_array($ResultSet)
    You do not need to specify a result set - it uses $this-QueryID
-----

* Escaping a string

db_string($str);
    Is a wrapper for $DB->escape_str(), which is a wrapper for
    mysqli_real_escape_string(). The db_string() function exists so that you
    don't have to keep calling $DB->escape_str().

    USE THIS FUNCTION EVERY TIME YOU USE AN UNVALIDATED USER-SUPPLIED VALUE IN
    A DATABASE QUERY!


//--------- Advanced usage ---------------------------------------------------------

* The conventional way of retrieving a row from a result set is as follows:

list($All, $Columns, $That, $You, $Select) = $DB->next_record();
-----

* This is how you loop over the result set:

while (list($All, $Columns, $That, $You, $Select) = $DB->next_record()) {
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
    $DB->query().

    Example:

    $FoodRS = $DB->query("
            SELECT *
            FROM food");
    $DB->query("
        SELECT *
        FROM drink");
    $Drinks = $DB->next_record();
    $DB->set_query_id($FoodRS);
    $Food = $DB->next_record();

    Of course, this example is contrived, but you get the point.


-------------------------------------------------------------------------------------
*///---------------------------------------------------------------------------------

if (!extension_loaded('mysqli')) {
    throw new Exception('Mysqli Extension not loaded.');
}

//Handles escaping
function db_string($String, $DisableWildcards = false) {
    global $DB;
    //Escape
    $String = $DB->escape_str($String);
    //Remove user input wildcards
    if ($DisableWildcards) {
        $String = str_replace(['%','_'], ['\%','\_'], $String);
    }
    return $String;
}

use \Gazelle\Util\Irc;

class DB_MYSQL_Exception extends Exception {}
class DB_MYSQL_DuplicateKeyException extends DB_MYSQL_Exception {}

//TODO: revisit access levels once Drone is replaced by ZeRobot
class DB_MYSQL {
    /** @var mysqli|bool */
    public $LinkID = false;
    /** @var mysqli_result|bool */
    protected $QueryID = false;
    protected $Record = [];
    protected $Row;
    protected $Errno = 0;
    protected $Error = '';
    protected bool $queryLog = true;

    protected $PreparedQuery = null;
    protected $Statement = null;

    public $Queries = [];
    public $Time = 0.0;

    protected $Database = '';
    protected $Server = '';
    protected $User = '';
    protected $Pass = '';
    protected $Port = 0;
    protected $Socket = '';

    public function __construct($Database = SQLDB, $User = SQLLOGIN, $Pass = SQLPASS, $Server = SQLHOST, $Port = SQLPORT, $Socket = SQLSOCK) {
        $this->Database = $Database;
        $this->Server = $Server;
        $this->User = $User;
        $this->Pass = $Pass;
        $this->Port = $Port;
        $this->Socket = $Socket;
    }

    public function disableQueryLog() {
        $this->queryLog = false;
    }

    public function enableQueryLog() {
        $this->queryLog = true;
    }

    private function halt($Msg) {
        if ($this->Errno == 1062) {
            throw new DB_MYSQL_DuplicateKeyException;
        }
        global $Debug;
        $DBError = 'MySQL: '.strval($Msg).' SQL error: '.strval($this->Errno).' ('.strval($this->Error).')';
        if ($this->Errno == 1194) {
            Irc::sendRaw('PRIVMSG ' . ADMIN_CHAN . ' :' . $DBError);
        }
        $Debug->analysis('!dev DB Error', $DBError, 3600 * 24);
        throw new DB_MYSQL_Exception($DBError);
    }

    public function connect() {
        if (!$this->LinkID) {
            $this->LinkID = mysqli_connect($this->Server, $this->User, $this->Pass, $this->Database, $this->Port, $this->Socket); // defined in config.php
            if (!$this->LinkID) {
                $this->Errno = mysqli_connect_errno();
                $this->Error = mysqli_connect_error();
                $this->halt('Connection failed (host:'.$this->Server.':'.$this->Port.')');
            }
        }
    }

    private function setup_query() {
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
        if ($this->QueryID) {
            $this->warnings();
        }

        $this->connect();
    }

    /**
     * Runs a raw query assuming pre-sanitized input. However, attempting to self sanitize (such
     * as via db_string) is still not as safe for using prepared statements so for queries
     * involving user input, you really should not use this function (instead opting for
     * prepared_query) {@See DB_MYSQL::prepared_query}
     *
     * When running a batch of queries using the same statement
     * with a variety of inputs, it's more performant to reuse the statement
     * with {@see DB_MYSQL::prepare} and {@see DB_MYSQL::execute}
     *
     * @return mysqli_result|bool Returns a mysqli_result object
     *                            for successful SELECT queries,
     *                            or TRUE for other successful DML queries
     *                            or FALSE on failure.
     *
     * @param string $Query
     * @param int $AutoHandle
     * @return mysqli_result|bool
     */
    public function query($Query, $AutoHandle=1) {
        $this->setup_query();

        $Closure = function() use ($Query) {
            return mysqli_query($this->LinkID, $Query);
        };

        return $this->attempt_query($Query, $Closure, $AutoHandle);
    }

    /**
     * Prepares an SQL statement for execution with data.
     *
     * Normally, you'll most likely just want to be using
     * DB_MYSQL::prepared_query to call both DB_MYSQL::prepare
     * and DB_MYSQL::execute for one-off queries, you can use
     * this separately in the case where you plan to be running
     * this query repeatedly while just changing the bound
     * parameters (such as if doing a bulk update or the like).
     *
     * @return mysqli_stmt|bool Returns a statement object
     *                          or FALSE if an error occurred.
     */
    public function prepare($Query) {
        $this->setup_query();
        $Query = trim($Query);
        $this->PreparedQuery = $Query;
        $this->Statement = $this->LinkID->prepare($Query);
        $this->Errno = $this->LinkID->errno;
        $this->Error = $this->LinkID->error;
        if ($this->Statement === false) {
            $this->Queries[] = ["$Query /* ERROR: {$this->Error} */", 0, null];
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
     *
     * @param  array $Parameters,... variables for the query
     * @return mysqli_result|bool Returns a mysqli_result object
     *                            for successful SELECT queries,
     *                            or TRUE for other successful DML queries
     *                            or FALSE on failure.
     */
    public function execute(...$Parameters) {
        /** @var mysqli_stmt $Statement */
        $Statement = &$this->Statement;

        if (count($Parameters) > 0) {
            $Binders = "";
            foreach ($Parameters as $Parameter) {
                if (is_integer($Parameter)) {
                    $Binders .= "i";
                }
                elseif (is_double($Parameter)) {
                    $Binders .= "d";
                }
                else {
                    $Binders .= "s";
                }
            }
            $Statement->bind_param($Binders, ...$Parameters);
        }

        $Closure = function() use ($Statement) {
            $Statement->execute();
            return $Statement->get_result();
        };

        $Query = $this->PreparedQuery . ' -- ' . json_encode($Parameters);
        return $this->attempt_query($Query, $Closure);
    }

    /**
     * Prepare and execute a prepared query returning the result set.
     *
     * Utility function that wraps DB_MYSQL::prepare and DB_MYSQL::execute
     * as most times, the query is going to be one-off and this will save
     * on keystrokes. If you do plan to be executing a prepared query
     * multiple times with different bound parameters, you'll want to call
     * the two functions separately instead of this function.
     *
     * @param string $Query
     * @param mixed ...$Parameters
     * @return bool|mysqli_result
     */
    public function prepared_query($Query, ...$Parameters) {
        $this->prepare($Query);
        return $this->execute(...$Parameters);
    }

    private function attempt_query($Query, Callable $Closure, $AutoHandle=1) {
        global $Debug;
        $QueryStartTime = microtime(true);
        // In the event of a MySQL deadlock, we sleep allowing MySQL time to unlock, then attempt again for a maximum of 5 tries
        for ($i = 1; $i < 6; $i++) {
            $this->QueryID = $Closure();
            if (!in_array(mysqli_errno($this->LinkID), [1213, 1205])) {
                break;
            }
            $Debug->analysis('Non-Fatal Deadlock:', $Query, 3600 * 24);
            trigger_error("Database deadlock, attempt $i");

            sleep($i * rand(2, 5)); // Wait longer as attempts increase
        }
        $QueryEndTime = microtime(true);
        // Kills admin pages, and prevents Debug->analysis when the whole set exceeds 1 MB
        if (($Len = strlen($Query))>16384) {
            $Query = substr($Query, 0, 16384).'... '.($Len-16384).' bytes trimmed';
        }
        if ($this->queryLog) {
            $this->Queries[] = [$Query, ($QueryEndTime - $QueryStartTime) * 1000, null];
        }
        $this->Time += ($QueryEndTime - $QueryStartTime) * 1000;

        // Update/Insert/etc statements for prepared queries don't return a QueryID,
        // but mysqli_errno is also going to be 0 for no error
        $this->Errno = mysqli_errno($this->LinkID);
        if (!$this->QueryID && $this->Errno !== 0) {
            $this->Error = mysqli_error($this->LinkID);

            if ($AutoHandle) {
                $this->halt("Invalid Query: $Query");
            } else {
                return $this->Errno;
            }
        }

        $this->Row = 0;
        if ($AutoHandle) {
            return $this->QueryID;
        }
    }

    public function inserted_id() {
        if ($this->LinkID) {
            return mysqli_insert_id($this->LinkID);
        }
    }

    public function next_row($type = MYSQLI_NUM) {
        if ($this->LinkID) {
            return mysqli_fetch_array($this->QueryID, $type);
        }
        return null;
    }

    public function next_record($Type = MYSQLI_BOTH, $Escape = true, $Reverse = false) {
        // $Escape can be true, false, or an array of keys to not escape
        // If $Reverse is true, then $Escape is an array of keys to escape
        if ($this->LinkID) {
            $this->Record = mysqli_fetch_array($this->QueryID, $Type);
            $this->Row++;
            if (!is_array($this->Record)) {
                $this->QueryID = false;
            } elseif ($Escape !== false) {
                $this->Record = $this->display_array($this->Record, $Escape, $Reverse);
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
     * @param mixed  $Escape Boolean true/false for escaping entire/none of query
     *                          or can be an array of array keys for what columns to escape
     * @return array next result set if exists
     */
    public function fetch_record(...$Escape) {
        if (count($Escape) === 1 && $Escape[0] === true) {
            $Escape = true;
        }
        elseif (count($Escape) === 0) {
            $Escape = false;
        }
        return $this->next_record(MYSQLI_BOTH, $Escape, true);
    }

    public function close() {
        if ($this->LinkID) {
            if (!mysqli_close($this->LinkID)) {
                $this->halt('Cannot close connection or connection did not open.');
            }
            $this->LinkID = false;
        }
    }

    /*
     * returns an integer with the number of rows found
     * returns a string if the number of rows found exceeds MAXINT
     */
    public function record_count() {
        if ($this->QueryID) {
            return mysqli_num_rows($this->QueryID);
        }
    }

    /*
     * returns true if the query exists and there were records found
     * returns false if the query does not exist or if there were 0 records returned
     */
    public function has_results() {
        return ($this->QueryID && $this->record_count() !== 0);
    }

    public function affected_rows() {
        if ($this->LinkID) {
            return $this->LinkID->affected_rows;
        }
        /* why the fuck is this necessary for \Gazelle\Bonus\purchaseInvite() ?! */
        if ($this->Statement) {
            return $this->Statement->affected_rows;
        }
        return 0;
    }

    public function info() {
        return mysqli_get_host_info($this->LinkID);
    }

    // You should use db_string() instead.
    public function escape_str($Str) {
        $this->connect();
        if (is_array($Str)) {
            trigger_error('Attempted to escape array.');
            return '';
        }
        return mysqli_real_escape_string($this->LinkID, $Str);
    }

    // Creates an array from a result set
    // If $Key is set, use the $Key column in the result set as the array key
    // Otherwise, use an integer
    public function to_array($Key = false, $Type = MYSQLI_BOTH, $Escape = true) {
        $Return = [];
        while ($Row = mysqli_fetch_array($this->QueryID, $Type)) {
            if ($Escape !== false) {
                $Row = $this->display_array($Row, $Escape);
            }
            if ($Key !== false) {
                $Return[$Row[$Key]] = $Row;
            } else {
                $Return[] = $Row;
            }
        }
        mysqli_data_seek($this->QueryID, 0);
        return $Return;
    }

    //  Loops through the result set, collecting the $ValField column into an array with $KeyField as keys
    public function to_pair($KeyField, $ValField, $Escape = true) {
        $Return = [];
        while ($Row = mysqli_fetch_array($this->QueryID)) {
            if ($Escape) {
                $Key = display_str($Row[$KeyField]);
                $Val = display_str($Row[$ValField]);
            } else {
                $Key = $Row[$KeyField];
                $Val = $Row[$ValField];
            }
            $Return[$Key] = $Val;
        }
        mysqli_data_seek($this->QueryID, 0);
        return $Return;
    }

    //  Loops through the result set, collecting the $Key column into an array
    public function collect($Key, $Escape = true) {
        $Return = [];
        while ($Row = mysqli_fetch_array($this->QueryID)) {
            $Return[] = $Escape ? display_str($Row[$Key]) : $Row[$Key];
        }
        mysqli_data_seek($this->QueryID, 0);
        return $Return;
    }

    /**
     * Runs a prepared_query using placeholders and returns the matched row.
     * Stashes the current query id so that this can be used within a block
     * that is looping over an active resultset.
     *
     * @param string  $sql The parameterized query to run
     * @param mixed   $args  The values of the placeholders
     * @return array  resultset or null
     */
    public function row($sql, ...$args) {
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
     * @param string  $sql The parameterized query to run
     * @param mixed   $args  The values of the placeholders
     * @return array  key=>value resultset or null
     */
    public function rowAssoc($sql, ...$args) {
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
     *
     * @param string  $sql The parameterized query to run
     * @param mixed   $args  The values of the placeholders
     * @return mixed  value or null
     */
    public function scalar($sql, ...$args) {
        $qid = $this->get_query_id();
        $this->prepared_query($sql, ...$args);
        $result = $this->has_results() ? $this->next_record(MYSQLI_NUM, false) : [null];
        $this->set_query_id($qid);
        return $result[0];
    }

    public function set_query_id(&$ResultSet) {
        $this->QueryID = $ResultSet;
        $this->Row = 0;
    }

    public function get_query_id() {
        return $this->QueryID;
    }

    public function beginning() {
        mysqli_data_seek($this->QueryID, 0);
        $this->Row = 0;
    }

    /**
     * This function determines whether the last query caused warning messages
     * and stores them in $this->Queries.
     */
    public function warnings() {
        $Warnings = [];
        if ($this->LinkID !== false && mysqli_warning_count($this->LinkID)) {
            $e = mysqli_get_warnings($this->LinkID);
            do {
                if ($e->errno == 1592) {
                    // 1592: Unsafe statement written to the binary log using statement format since BINLOG_FORMAT = STATEMENT.
                    continue;
                }
                $Warnings[] = 'Code ' . $e->errno . ': ' . display_str($e->message);
            } while ($e->next());
        }
        $this->Queries[count($this->Queries) - 1][2] = $Warnings;
    }

    public function begin_transaction() {
        if (!$this->LinkID) {
            $this->connect();
        }
        mysqli_begin_transaction($this->LinkID);
    }

    public function commit() {
        mysqli_commit($this->LinkID);
    }

    public function rollback() {
        mysqli_rollback($this->LinkID);
    }

    /**
     * HTML escape an entire array for output.
     * @param array $Array, what we want to escape
     * @param boolean|array $Escape
     *    if true, all keys escaped
     *    if false, no escaping.
     *    If array, it's a list of array keys not to escape.
     * @param boolean $Reverse reverses $Escape such that then it's an array of keys to escape
     * @return array mutated version of $Array with values escaped.
     */
    protected function display_array($Array, $Escape = [], $Reverse = false) {
        foreach ($Array as $Key => $Val) {
            if ((!is_array($Escape) && $Escape == true) || (!$Reverse && !in_array($Key, $Escape)) || ($Reverse && in_array($Key, $Escape))) {
                $Array[$Key] = display_str($Val);
            }
        }
        return $Array;
    }
}
