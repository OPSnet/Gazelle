<?php
/**
 * Class for functions related to the features involving torrent downloads
 */
class TorrentsDL {
    const ChunkSize = 100;
    const MaxPathLength = 200;
    private $QueryResult;
    private $QueryRowNum = 0;
    private $Zip;
    private $IDBoundaries;
    private $FailedFiles = [];
    private $NumAdded = 0;
    private $NumFound = 0;
    private $Size = 0;
    private $Title;
    private $User;
    private $AnnounceURL;
    private $SkippedFiles = [];

    /**
     * Create a Zip object and store the query results
     *
     * @param mysqli_result $QueryResult results from a query on the collector pages
     * @param string $Title name of the collection that will be created
     */
    public function __construct(&$QueryResult, $Title) {
        global $Cache, $LoggedUser;
        $Cache->InternalCache = false; // The internal cache is almost completely useless for this
        $this->QueryResult = $QueryResult;
        $this->Title = $Title;
        $this->User = $LoggedUser;
        $this->AnnounceURL = (new \Gazelle\User($LoggedUser['ID']))->announceUrl();
        $options = new ZipStream\Option\Archive;
        $options->setSendHttpHeaders(true);
        $options->setEnableZip64(false); // for macOS compatibility
        $options->setFlushOutput(true); // flush on each file to save on memory
        $this->Zip = new ZipStream\ZipStream(self::safeString($Title) . '.zip', $options);
    }

    /**
     * Sanitize a string to be allowed as a filename.
     *
     * @param string $EscapeStr the string to escape
     * @return the string with all banned characters removed.
     */
    public static function safeString(string $EscapeStr): string {
        return str_replace(['"', '*', '/', ':', '<', '>', '?', '\\', '|'], '', $EscapeStr);
    }

    /**
     * Store the results from a DB query in smaller chunks to save memory
     *
     * @param string $Key the key to use in the result hash map
     * @return array with results and torrent group IDs or false if there are no results left
     */
    public function get_downloads($Key) {
        $GroupIDs = $Downloads = [];
        global $Cache, $DB;
        $OldQuery = $DB->get_query_id();
        $DB->set_query_id($this->QueryResult);
        if (!isset($this->IDBoundaries)) {
            if ($Key == 'TorrentID') {
                $this->IDBoundaries = false;
            } else {
                $this->IDBoundaries = $DB->to_pair($Key, 'TorrentID', false);
            }
        }
        $Found = 0;
        while ($Download = $DB->next_record(MYSQLI_ASSOC, false)) {
            if (!$this->IDBoundaries || $Download['TorrentID'] == $this->IDBoundaries[$Download[$Key]]) {
                $Found++;
                $Downloads[$Download[$Key]] = $Download;
                $GroupIDs[$Download['TorrentID']] = $Download['GroupID'];
                if ($Found >= self::ChunkSize) {
                    break;
                }
            }
        }
        $this->NumFound += $Found;
        $DB->set_query_id($OldQuery);
        if (empty($Downloads)) {
            return false;
        }
        return [$Downloads, $GroupIDs];
    }

    /**
     * Add a file to the zip archive
     *
     * @param string $TorrentData bencoded torrent without announce url
     * @param array $Info file info stored as an array with at least the keys
     *  Artist, Name, Year, Media, Format, Encoding and TorrentID
     * @param string $FolderName folder name
     */
    public function add_file(&$TorrentData, $Info, $FolderName = '') {
        $FolderName = self::safeString($FolderName);
        $MaxPathLength = $FolderName ? (self::MaxPathLength - strlen($FolderName) - 1) : self::MaxPathLength;
        $FileName = self::construct_file_name($Info['Artist'], $Info['Name'], $Info['Year'], $Info['Media'], $Info['Format'], $Info['Encoding'], $Info['TorrentID'], false, $MaxPathLength);
        $this->Size += $Info['Size'];
        $this->NumAdded++;
        $this->Zip->addFile(($FolderName ? "$FolderName/" : "") . $FileName, self::get_file($TorrentData, $this->AnnounceURL, $Info['TorrentID']));
    }

    /**
     * Add a file to the list of files that could not be downloaded
     *
     * @param array $Info file info stored as an array with at least the keys Artist, Name and Year
     */
    public function fail_file($Info) {
        $this->FailedFiles[] = $Info['Artist'] . $Info['Name'] . " " . $Info['Year'];
    }

    /**
     * Add a file to the list of files that did not match the user's format or quality requirements
     *
     * @param array $Info file info stored as an array with at least the keys Artist, Name and Year
     */
    public function skip_file($Info) {
        $this->SkippedFiles[] = $Info['Artist'] . $Info['Name'] . " " . $Info['Year'];
    }

    /**
     * Add a summary to the archive and include a list of files that could not be added. Close the zip archive
     *
     * @param bool $FilterStats whether to include filter stats in the report
     */
    public function finalize($FilterStats = true) {
        $this->Zip->addFile("Summary.txt", $this->summary($FilterStats));
        if (!empty($this->FailedFiles)) {
            $this->Zip->addFile("Errors.txt", $this->errors());
        }
        $this->Zip->finish();
    }

    /**
     * Produce a summary text over the collector results
     *
     * @param bool $FilterStats whether to include filter stats in the report
     * @return string summary text
     */
    public function summary($FilterStats) {
        $Time = number_format(1000 * (microtime(true) - $debug->startTime()), 2)." ms";
        $Used = Format::get_size(memory_get_usage(true));
        $Date = date("M d Y, H:i");
        $NumSkipped = count($this->SkippedFiles);
        return "Collector Download Summary for $this->Title - " . SITE_NAME . "\r\n"
            . "\r\n"
            . "User:        {$this->User['Username']}\r\n"
            . "Passkey:    {$this->User['torrent_pass']}\r\n"
            . "\r\n"
            . "Time:        $Time\r\n"
            . "Used:        $Used\r\n"
            . "Date:        $Date\r\n"
            . "\r\n"
            . ($FilterStats !== false
                ? "Torrent groups analyzed:    $this->NumFound\r\n"
                    . "Torrent groups filtered:    $NumSkipped\r\n"
                : "")
            . "Torrents downloaded:        $this->NumAdded\r\n"
            . "\r\n"
            . "Total size of torrents (ratio hit): ".Format::get_size($this->Size)."\r\n"
            . ($NumSkipped
                ? "\r\n"
                    . "Albums unavailable within your criteria (consider making a request for your desired format):\r\n"
                    . implode("\r\n", $this->SkippedFiles) . "\r\n"
                : "");
    }

    /**
     * Compile a list of files that could not be added to the archive
     *
     * @return string list of files
     */
    public function errors() {
        return "A server error occurred. Please try again at a later time.\r\n"
            . "\r\n"
            . "The following torrents could not be downloaded:\r\n"
            . implode("\r\n", $this->FailedFiles) . "\r\n";
    }

    /**
     * Combine a bunch of torrent info into a standardized file name
     *
     * @params most input variables are self-explanatory
     * @param int $TorrentID if given, append "-TorrentID" to torrent name
     * @param bool $Txt whether to use .txt or .torrent as file extension
     * @param int $MaxLength maximum file name length
     * @return string file name with at most $MaxLength characters
     */
    public static function construct_file_name($Artist, $Album, $Year, $Media, $Format, $Encoding, $TorrentID = false, $Txt = false, $MaxLength = self::MaxPathLength) {
        $MaxLength -= ($Txt ? 4 : 8);
        if ($TorrentID !== false) {
            $MaxLength -= (strlen($TorrentID) + 1);
        }
        $TorrentArtist = self::safeString($Artist);
        $TorrentName = self::safeString($Album);
        if ($Year > 0) {
            $TorrentName .= " - $Year";
        }
        $TorrentInfo = [];
        if ($Media != '') {
            $TorrentInfo[] = $Media;
        }
        if ($Format != '') {
            $TorrentInfo[] = $Format;
        }
        if ($Encoding != '') {
            $TorrentInfo[] = $Encoding;
        }
        if (!empty($TorrentInfo)) {
            $TorrentInfo = ' (' . self::safeString(implode(' - ', $TorrentInfo)) . ')';
        } else {
            $TorrentInfo = '';
        }

        if (!$TorrentName) {
            $TorrentName = 'No Name';
        } elseif (mb_strlen($TorrentArtist . $TorrentName . $TorrentInfo, 'UTF-8') <= $MaxLength) {
            $TorrentName = $TorrentArtist . $TorrentName;
        }

        $TorrentName = shortenString($TorrentName . $TorrentInfo, $MaxLength, true, false);
        if ($TorrentID !== false) {
            $TorrentName .= "-$TorrentID";
        }
        if ($Txt) {
            return "$TorrentName.txt";
        }
        return "$TorrentName.torrent";
    }

    /**
     * Convert a stored torrent into a binary file that can be loaded in a torrent client
     *
     * @param mixed $TorrentData bencoded torrent without announce URL
     * @param string $AnnounceURL
     * @param int $TorrentID
     * @return string bencoded string
     */
    public static function get_file($TorrentData, $AnnounceURL, $TorrentID) {
        $Tor = new OrpheusNET\BencodeTorrent\BencodeTorrent();
        $Tor->decodeString($TorrentData);
        $Tor->cleanDataDictionary();
        $Tor->setValue([
            'announce' => $AnnounceURL,
            'comment' => SITE_URL . "/torrents.php?torrentid=$TorrentID",
        ]);
        return $Tor->getEncode();
    }
}
