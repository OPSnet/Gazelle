<?php

namespace Gazelle;

/*
This is something of a hack so those easily scared off by funky solutions,
don't touch it! :P

There is a central problem to this page, it's impossible to order before
grouping in SQL, and it's slow to run sub queries, so we had to get creative for
this one.

The solution I settled on abuses the way $DB->to_array() works. What we've done,
is backwards ordering. The results returned by the query have the best one for
each GroupID last, and while to_array traverses the results, it overwrites the
keys and leaves us with only the desired result. This does mean however, that
the SQL has to be done in a somewhat backwards fashion.

Thats all you get for a disclaimer, just remember, this page isn't for the faint
of heart. -A9

SQL template:
SELECT
    CASE
        WHEN t.Format = 'MP3' AND t.Encoding = 'V0 (VBR)' THEN 1
        WHEN t.Format = 'MP3' AND t.Encoding = 'V2 (VBR)' THEN 2
        ELSE 100 END AS Rank,
    t.GroupID,
    t.Media,
    t.Format,
    t.Encoding,
    if(t.Year = 0, tg.Year, t.Year),
    tg.Name,
    a.Name,
    t.Size
FROM torrents AS t
INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID AND tg.CategoryID = '1'
INNER JOIN artists_group AS a ON a.ArtistID = tg.ArtistID AND a.ArtistID = 123
ORDER BY t.GroupID ASC, Rank DESC, tls.Seeders ASC
*/

abstract class Collector extends Base  {
    const CHUNK_SIZE = 100;
    const ORDER_BY = ['t.RemasterTitle DESC', 'tls.Seeders ASC', 't.Size ASC'];

    protected $user;
    protected $orderBy;
    protected $sql  = '';
    protected $args = [];
    protected $qid  = false;
    protected $idBoundary;
    protected $totalAdded = 0;
    protected $totalFound = 0;
    protected $totalSize = 0;
    protected $totalTokens = 0;
    protected $error = [];
    protected $skipped = [];
    protected $startTime;
    protected $title;
    protected $zip;
    protected $torMan;

    abstract public function prepare(array $list);
    abstract public function fill();

    /**
     * Create a Zip object and store the query results
     *
     * @param string $title name of the collection that will be created
     */
    public function __construct(\Gazelle\User $user, $title, $orderBy) {
        parent::__construct();

        $this->cache->InternalCache = false; // The internal cache is almost completely useless for this
        $this->title = $title;
        $this->user = $user;
        $this->orderBy = $orderBy;
        $this->startTime = microtime(true);

        $options = new \ZipStream\Option\Archive;
        $options->setSendHttpHeaders(true);
        $options->setEnableZip64(false); // for macOS compatibility
        $options->setFlushOutput(true); // flush on each file to save on memory
        $options->setContentType('application/x-zip');
        $options->setDeflateLevel(8);
        $this->zip = new \ZipStream\ZipStream(SITE_NAME . '-' . safeFilename($title) . '.zip', $options);

        $this->torMan = new \Gazelle\Manager\Torrent;
    }

    /**
     * Collector query preamble
     *
     * return string beginning of SQL query to collect torrents
     */
    public function queryPreamble(array $list) {
        $sql = 'SELECT ';
        if (count($list) == 0) {
            $sql .= '0 AS Rank, ';
        } else {
            $sql .= 'CASE ';
            foreach ($list as $Priority => $Selection) {
                if (!is_number($Priority)) {
                    continue;
                }
                $sql .= 'WHEN ';
                switch ($Selection) {
                    case '00': $sql .= "t.Format = 'MP3'  AND t.Encoding = 'V0 (VBR)'"; break;
                    case '01': $sql .= "t.Format = 'MP3'  AND t.Encoding = 'APX (VBR)'"; break;
                    case '02': $sql .= "t.Format = 'MP3'  AND t.Encoding = '256 (VBR)'"; break;
                    case '03': $sql .= "t.Format = 'MP3'  AND t.Encoding = 'V1 (VBR)'"; break;
                    case '10': $sql .= "t.Format = 'MP3'  AND t.Encoding = '224 (VBR)'"; break;
                    case '11': $sql .= "t.Format = 'MP3'  AND t.Encoding = 'V2 (VBR)'"; break;
                    case '12': $sql .= "t.Format = 'MP3'  AND t.Encoding = 'APS (VBR)'"; break;
                    case '13': $sql .= "t.Format = 'MP3'  AND t.Encoding = '192 (VBR)'"; break;
                    case '20': $sql .= "t.Format = 'MP3'  AND t.Encoding = '320'"; break;
                    case '21': $sql .= "t.Format = 'MP3'  AND t.Encoding = '256'"; break;
                    case '22': $sql .= "t.Format = 'MP3'  AND t.Encoding = '224'"; break;
                    case '23': $sql .= "t.Format = 'MP3'  AND t.Encoding = '192'"; break;
                    case '24': $sql .= "t.Format = 'MP3'  AND t.Encoding = '160'"; break;
                    case '25': $sql .= "t.Format = 'MP3'  AND t.Encoding = '128'"; break;
                    case '26': $sql .= "t.Format = 'MP3'  AND t.Encoding = '96'"; break;
                    case '27': $sql .= "t.Format = 'MP3'  AND t.Encoding = '64'"; break;
                    case '30': $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'Vinyl'"; break;
                    case '31': $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'DVD'"; break;
                    case '32': $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'SACD'"; break;
                    case '33': $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'WEB'"; break;
                    case '34': $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND HasLog = '1' AND LogScore = '100' AND HasCue = '1'"; break;
                    case '35': $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND HasLog = '1' AND LogScore = '100'"; break;
                    case '36': $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND HasLog = '1'"; break;
                    case '37': $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND t.Media = 'WEB'"; break;
                    case '38': $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless'"; break;
                    case '40': $sql .= "t.Format = 'DTS'"; break;
                    case '42': $sql .= "t.Format = 'AAC'  AND t.Encoding = '320'"; break;
                    case '43': $sql .= "t.Format = 'AAC'  AND t.Encoding = '256'"; break;
                    case '44': $sql .= "t.Format = 'AAC'  AND t.Encoding = 'q5.5'"; break;
                    case '45': $sql .= "t.Format = 'AAC'  AND t.Encoding = 'q5'"; break;
                    case '46': $sql .= "t.Format = 'AAC'  AND t.Encoding = '192'"; break;
                    default: error(0);
                }
                $sql .= "THEN $Priority ";
            }
            $sql .= "ELSE 100 END AS Rank, ";
        }
        return $sql
            . "t.GroupID,
            t.ID AS TorrentID,
            t.Media,
            t.Format,
            t.Encoding,
            tg.ReleaseType,
            if(t.RemasterYear=0, tg.Year, t.RemasterYear) AS Year,
            tg.Name,
            t.Size";
    }

    /**
     * This method is called repeatedly after the query is prepared, and returns
     * the resultset in chunks, to avoid blowing out the memory requirements on
     * artists with many, many, many releases.
     *
     * @param string $Key the key to use in the result hash map
     * @return array with results and torrent group IDs
     */
    public function process(string $Key): array {
        $saveQid = $this->db->get_query_id();
        $this->db->set_query_id($this->qid);
        if (!isset($this->idBoundary)) {
            if ($Key == 'TorrentID') {
                $this->idBoundary = false;
            } else {
                $this->idBoundary = $this->db->to_pair($Key, 'TorrentID', false);
            }
        }
        $found = 0;
        $GroupIDs = [];
        $Downloads = [];
        while ($row = $this->db->next_record(MYSQLI_ASSOC, false)) {
            if (!$this->idBoundary || $row['TorrentID'] == $this->idBoundary[$row[$Key]]) {
                $found++;
                $Downloads[$row[$Key]] = $row;
                $GroupIDs[$row['TorrentID']] = $row['GroupID'];
                if ($found >= self::CHUNK_SIZE) {
                    break;
                }
            }
        }
        $this->totalFound += $found;
        $this->db->set_query_id($saveQid);
        return empty($Downloads) ? [null, null] : [$Downloads, $GroupIDs];
    }

    /**
     * Add a file to the zip archive. If the torrent file cannot be found
     * it will be added to the list of errors. If the torrent does not
     * match the minimum format/encoding requirements, it will be skipped.
     *
     * @param string $data bencoded torrent without announce url
     * @param array $info file info stored as an array with at least the keys
     *     Artist, Name, Year, Media, Format, Encoding and TorrentID
     * @param string $folderName folder name
     */
    public function add(array $info, $folderName = null) {
        if ($info['Rank'] == 100) {
            $this->skip($info);
            return;
        }
        $contents = $this->torMan->torrentBody($info['TorrentID'], $this->user->announceUrl());
        if ($contents === '') {
            $this->fail($info);
            return;
        }
        $folder = is_null($folderName) ? '' : (safeFilename($folderName) . '/');
        $name = $this->torMan->torrentFilename($info, false, MAX_PATH_LEN - strlen($folder));
        $this->zip->addFile("$folder$name", $contents);

        $this->totalAdded++;
        $this->totalSize += $info['Size'];
        $this->totalTokens += (int)ceil($info['Size'] / BYTES_PER_FREELEECH_TOKEN);
    }

    /**
     * Add a file to the list of files that did not match the user's format or quality requirements
     *
     * @param array containing keys Artist, Name and Year
     */
    public function skip($info) {
        $this->skipped[] = "{$info['Artist']}/{$info['Year']}/{$info['Name']}";
        return $this;
    }

    /**
     * Add a file to the list of files for which the torrent data is corrupt.
     *
     * @param array containing keys Artist, Name and Year
     */
    public function fail($info) {
        $this->error[] = "{$info['Artist']}/{$info['Year']}/{$info['Name']}";
    }

    /**
     * Compile a list of files that could not be added to the archive
     *
     * @return string Summary of error files
     */
    public function errors() {
        return "The following torrents are in an broken or missing. This is bad!"
            . "\r\n"
            . implode("\r\n", $this->error) . "\r\n";
    }

    /**
     * Add a summary to the archive and include a list of files that could not be added. Close the zip archive
     *
     * @param bool $FilterStats whether to include filter stats in the report
     */
    public function emit() {
        $this->fill();
        $folder = safeFilename($this->title);
        $this->zip->addFile("README.txt", $this->summary());
        if ($this->error) {
            $this->zip->addFile("ERRORS.txt", $this->errors());
        }
        $this->zip->finish();
    }

    /**
     * Produce a summary text over the collector results
     *
     * @param bool $FilterStats whether to include filter stats in the report
     * @return string summary text
     */
    public function summary() {
        return $this->twig->render('collector.twig', [
            'added'   => $this->totalAdded,
            'total'   => $this->totalFound,
            'size'    => $this->totalSize,
            'tokens'  => $this->totalTokens,
            'error'   => count($this->error),
            'skipped' => $this->skipped,
            'title'   => $this->title,
            'date'    => date("Y-m-d H:i"),
            'time'    => 1000 * (microtime(true) - $this->startTime),
            'used'    => memory_get_usage(true),
            'user'    => $this->user,
        ]);
    }

    public function sql(): string {
        return $this->sql;
    }

    public function args(): array {
        return $this->args;
    }
}
