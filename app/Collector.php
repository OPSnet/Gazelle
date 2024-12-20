<?php

namespace Gazelle;

/*
This is something of a hack so those easily scared off by funky solutions,
don't touch it! :P

There is a central problem to this page, it's impossible to order before
grouping in SQL, and it's slow to run sub queries, so we had to get creative for
this one.

The solution I settled on abuses the way to_array() works. What we've done,
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
        ELSE 100 END AS sequence,
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
ORDER BY t.GroupID ASC, sequence DESC, tls.Seeders ASC
*/

abstract class Collector extends Base  {
    final public const CHUNK_SIZE = 100;
    final public const ORDER_BY = ['t.RemasterTitle DESC', 'tls.Seeders ASC', 't.Size ASC'];

    protected string $sql  = '';
    protected array $args = [];
    protected \mysqli_result|bool $qid  = false;
    protected array|false $idBoundary;
    protected int $totalAdded = 0;
    protected int $totalFound = 0;
    protected int $totalSize = 0;
    protected int $totalTokens = 0;
    protected array $error = [];
    protected array $skipped = [];
    protected float $startTime;

    abstract public function prepare(array $list): bool;
    abstract public function fillZip(\ZipStream\ZipStream $zip): int;

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\Torrent $torMan,
        protected readonly string          $title,
        protected int                      $orderBy,
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Collector query preamble
     *
     * return string beginning of SQL query to collect torrents
     */
    public function queryPreamble(array $list): string {
        $sql = 'SELECT ';
        if (count($list) == 0) {
            $sql .= '0 AS sequence, ';
        } else {
            $sql .= 'CASE ';
            foreach ($list as $Priority => $Selection) {
                if (!is_number($Priority)) {
                    continue;
                }
                $sql .= 'WHEN ';
                match ($Selection) {
                    '00' => $sql .= "t.Format = 'MP3'  AND t.Encoding = 'V0 (VBR)'",
                    '01' => $sql .= "t.Format = 'MP3'  AND t.Encoding = 'APX (VBR)'",
                    '02' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '256 (VBR)'",
                    '03' => $sql .= "t.Format = 'MP3'  AND t.Encoding = 'V1 (VBR)'",
                    '10' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '224 (VBR)'",
                    '11' => $sql .= "t.Format = 'MP3'  AND t.Encoding = 'V2 (VBR)'",
                    '12' => $sql .= "t.Format = 'MP3'  AND t.Encoding = 'APS (VBR)'",
                    '13' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '192 (VBR)'",
                    '20' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '320'",
                    '21' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '256'",
                    '22' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '224'",
                    '23' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '192'",
                    '24' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '160'",
                    '25' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '128'",
                    '26' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '96'",
                    '27' => $sql .= "t.Format = 'MP3'  AND t.Encoding = '64'",
                    '30' => $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'Vinyl'",
                    '31' => $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'DVD'",
                    '32' => $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'SACD'",
                    '33' => $sql .= "t.Format = 'FLAC' AND t.Encoding = '24bit Lossless' AND t.Media = 'WEB'",
                    '34' => $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND HasLog = '1' AND LogScore = '100' AND HasCue = '1'",
                    '35' => $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND HasLog = '1' AND LogScore = '100'",
                    '36' => $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND HasLog = '1'",
                    '37' => $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless' AND t.Media = 'WEB'",
                    '38' => $sql .= "t.Format = 'FLAC' AND t.Encoding = 'Lossless'",
                    '40' => $sql .= "t.Format = 'DTS'",
                    '42' => $sql .= "t.Format = 'AAC'  AND t.Encoding = '320'",
                    '43' => $sql .= "t.Format = 'AAC'  AND t.Encoding = '256'",
                    '44' => $sql .= "t.Format = 'AAC'  AND t.Encoding = 'q5.5'",
                    '45' => $sql .= "t.Format = 'AAC'  AND t.Encoding = 'q5'",
                    '46' => $sql .= "t.Format = 'AAC'  AND t.Encoding = '192'",
                    default => error('Unknown collector selector'),
                };
                $sql .= " THEN $Priority ";
            }
            $sql .= "ELSE 100 END AS sequence, ";
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
     */
    public function process(string $Key): array|null {
        $saveQid = self::$db->get_query_id();
        self::$db->set_query_id($this->qid);
        if (!isset($this->idBoundary)) {
            if ($Key == 'TorrentID') {
                $this->idBoundary = false;
            } else {
                $this->idBoundary = self::$db->to_pair($Key, 'TorrentID', false);
            }
        }
        $downloadList = [];
        $insertArgs = [];
        while ($row = self::$db->next_record(MYSQLI_ASSOC, false)) {
            if (!$this->idBoundary || $row['TorrentID'] == $this->idBoundary[$row[$Key]]) {
                $downloadList[$row[$Key]] = $row;
                array_push($insertArgs, $this->user->id(), $row['TorrentID']);
                if (count($downloadList) >= self::CHUNK_SIZE) {
                    break;
                }
            }
        }
        $found = (int)(count($insertArgs) / 2);
        $this->totalFound += $found;

        if ($insertArgs) {
            // Record the files as having been downloaded, in order to take them
            // into account in the download factor. Ideally the entire collector
            // operation should take place in a transaction, in case of an abort
            // half way through. Maybe some other day.
            self::$db->prepared_query(
                "INSERT INTO users_downloads (UserID, TorrentID) VALUES"
                . implode(',', array_fill(0, $found, '(?,?)')),
                ...$insertArgs
            );
        }
        self::$db->set_query_id($saveQid);
        return $this->totalFound > 0 ? $downloadList : null;
    }

    /**
     * Add a file to the zip archive. If the torrent file cannot be found
     * it will be added to the list of errors. If the torrent does not
     * match the minimum format/encoding requirements, it will be skipped.
     *
     * $info file info stored as an array with at least the keys
     *     Artist, Name, Year, Media, Format, Encoding and TorrentID
     */
    public function addZip(\ZipStream\ZipStream $zip, array $info, string $folderName = null): void {
        if ($info['sequence'] == 100) {
            $this->skip($info);
            return;
        }
        $torrent = $this->torMan->findById($info['TorrentID']);
        if (is_null($torrent)) {
            $this->fail($info);
            return;
        }
        $contents = $torrent->torrentBody($this->user->announceUrl());
        if ($contents === '') {
            $this->fail($info);
            return;
        }

        $folder = is_null($folderName) ? '' : (safeFilename($folderName) . '/');
        $name = $torrent->torrentFilename(false, MAX_PATH_LEN - strlen($folder));
        $zip->addFile("$folder$name", $contents);

        $this->totalAdded++;
        $this->totalSize += $info['Size'];
        $this->totalTokens += (int)ceil($info['Size'] / BYTES_PER_FREELEECH_TOKEN);
    }

    /**
     * Add a file to the list of files that did not match the user's format or quality requirements
     */
    public function skip(array $info): static {
        $this->skipped[] = "{$info['Artist']}/{$info['Year']}/{$info['Name']}";
        return $this;
    }

    /**
     * Add a file to the list of files for which the torrent data is corrupt.
     */
    public function fail(array $info): static {
        $this->error[] = "{$info['Artist']}/{$info['Year']}/{$info['Name']}";
        return $this;
    }

    /**
     * Compile a list of files that could not be added to the archive
     */
    public function errors(): string {
        return "The following torrents are in an broken or missing. This is bad!"
            . "\r\n"
            . implode("\r\n", $this->error) . "\r\n";
    }

    /**
     * Add a summary to the archive and include a list of files that could not be added. Close the zip archive
     */
    public function emitZip(\ZipStream\ZipStream $zip): void {
        $this->fillZip($zip);
        $zip->addFile("README.txt", $this->summary());
        if ($this->error) {
            $zip->addFile("ERRORS.txt", $this->errors());
        }
        header('X-Accel-Buffering: no');
        $zip->finish();
    }

    /**
     * Produce a summary text over the collector results
     */
    public function summary(): string {
        return self::$twig->render('collector.twig', [
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
