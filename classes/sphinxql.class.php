<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

if (!extension_loaded('mysqli')) {
    error('Mysqli Extension not loaded.');
}

class Sphinxql extends mysqli {
    private static $Connections = [];
    private $Ident;
    private $Connected = false;

    public static $Queries = [];
    public static $Time = 0.0;


    /**
     * Initialize Sphinxql object
     *
     * @param string $Server server address or hostname
     * @param int $Port listening port
     * @param string $Socket Unix socket address, overrides $Server:$Port
     */
    public function __construct(private $Server, private $Port, private $Socket) {
        $this->Ident = self::get_ident($this->Server, $this->Port, $this->Socket);
    }

    /**
     * Create server ident based on connection information
     *
     * @param string $Server server address or hostname
     * @param int $Port listening port
     * @param string $Socket Unix socket address, overrides $Server:$Port
     * @return string identification string
     */
    private static function get_ident($Server, $Port, $Socket) {
        if ($Socket) {
            return $Socket;
        } else {
            return "$Server:$Port";
        }
    }

    /**
     * Create Sphinxql object or return existing one
     *
     * @param string $Server server address or hostname
     * @param int $Port listening port
     * @param string $Socket Unix socket address, overrides $Server:$Port
     * @return Sphinxql object
     */
    public static function init_connection($Server, $Port, $Socket) {
        $Ident = self::get_ident($Server, $Port, $Socket);
        if (!isset(self::$Connections[$Ident])) {
            self::$Connections[$Ident] = new Sphinxql($Server, $Port, $Socket);
        }
        return self::$Connections[$Ident];
    }

    /**
     * Connect the Sphinxql object to the Sphinx server
     */
    public function sph_connect() {
        if ($this->Connected || $this->connect_errno) {
            return;
        }
        global $Debug;
        $Debug->mark("Connecting to Sphinx server $this->Ident");
        for ($Attempt = 0; $Attempt < 3; $Attempt++) {
            parent::__construct($this->Server, '', '', '', $this->Port, $this->Socket);
            if (!$this->connect_errno) {
                $this->Connected = true;
                break;
            }
            sleep(1);
        }
        if ($this->connect_errno) {
            $Errno = $this->connect_errno;
            $Error = $this->connect_error;
            $this->error("Connection failed. (" . strval($Errno) . ": " . strval($Error) . ")");
            $Debug->mark("Could not connect to Sphinx server $this->Ident. (" . strval($Errno) . ": " . strval($Error) . ")");
        } else {
            $Debug->mark("Connected to Sphinx server $this->Ident");
        }
    }

    /**
     * Print a message to privileged users and optionally halt page processing
     */
    public function error(string $message, bool $halt = false) {
        global $Debug, $Viewer;
        $error = "SphinxQL ({$this->Ident}): $message";
        $Debug->analysis(
            $Viewer->requestContext()->module(),
            'SphinxQL Error',
            $error,
            86_400,
        );
        if ($halt === true) {
            if (DEBUG_MODE || $Viewer->permitted('site_debug')) {
                echo '<pre>' . display_str($error) . '</pre>';
                die();
            } else {
                error('-1');
            }
        }
    }

    /**
     * Escape special characters before sending them to the Sphinx server.
     * Two escapes needed because the first one is eaten up by the mysql driver.
     * Lowercase ASCII characters because some Sphinx operators are all caps words.
     *
     * @param string $String string to escape
     * @return string escaped string
     */
    public static function sph_escape_string($String) {
        return strtr(strtolower($String), [
            '(' => '\\\\(',
            ')' => '\\\\)',
            '|' => '\\\\|',
            '-' => '\\\\-',
            '@' => '\\\\@',
            '~' => '\\\\~',
            '&' => '\\\\&',
            '\'' => '\\\'',
            '<' => '\\\\<',
            '!' => '\\\\!',
            '"' => '\\\\"',
            '/' => '\\\\/',
            '*' => '\\\\*',
            '$' => '\\\\$',
            '^' => '\\\\^',
            '\\' => '\\\\\\\\']
        );
    }

    /**
     * Register sent queries globally for later retrieval by debug functions
     *
     * @param string $QueryString query text
     * @param float $QueryProcessTime time building and processing the query
     */
    public static function register_query($QueryString, $QueryProcessTime) {
        self::$Queries[] = [$QueryString, $QueryProcessTime];
        self::$Time += $QueryProcessTime;
    }
}
