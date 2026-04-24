<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\RiplogCollection;
use Gazelle\Torrent        as Torrent;
use Gazelle\TorrentDeleted as TorrentDeleted;
use Gazelle\User           as User;
use OrpheusNET\Logchecker\Check\Checksum;
use OrpheusNET\Logchecker\Logchecker;

/* NB: torrents do not know how to update their log score. (Most torrents
 * do not even have one). As such, all information derived from riplogs
 * that apply to torrents is centralized here.
 */

class Riplog extends \Gazelle\Base {
    /**
     * Persist a riplog to the database and file storage. This
     * method does not prevent a duplicate riplog from being
     * added to the torrent, the client code is responsible for
     * that. See the bulkCreate() method for one approach.
     */
    public function create(
        Torrent     $torrent,
        string      $filepath,
        string|null $filename = null,
    ): \Gazelle\Riplog {
        $parsed = $this->parse($filepath);
        self::$db->prepared_query('
            INSERT INTO torrents_logs
                   (TorrentID, Score, `Checksum`, Ripper, RipperVersion, `Language`, ChecksumState, Details, FileName, LogcheckerVersion)
            VALUES (?,         ?,      ?,         ?,      ?,             ?,          ?,             ?,       ?,        ?)
            ', $torrent->id,
                max(0, $parsed->getScore()),
                $parsed->getChecksumState() === Checksum::CHECKSUM_OK ? '1' : '0',
                $parsed->getRipper(),
                $parsed->getRipperVersion(),
                $parsed->getLanguage(),
                $parsed->getChecksumState(),
                implode("\r\n", $parsed->getDetails()),
                $filename,
                $this->logcheckerVersion(),
        );
        $riplog = new \Gazelle\Riplog($torrent, self::$db->inserted_id());
        $riplog->file()->put($filepath);
        $riplog->html()->put($parsed->getLog());
        return $riplog;
    }

    public function bulkCreate(
        Torrent $torrent,
        array   $uploadList,
    ): array {
        $seen       = $torrent->riplogHashList();
        $riplogList = [];
        for ($n = 0, $end = count($uploadList['error']); $n < $end; ++$n) {
            if ($uploadList['error'][$n] === UPLOAD_ERR_OK) {
                $hash = hash_file(DIGEST_ALGO, $uploadList['tmp_name'][$n]);
                if (in_array($hash, $seen)) {
                    continue;
                }
                $riplogList[] = $this->create(
                    $torrent,
                    $uploadList['tmp_name'][$n], // path
                    $uploadList['name'][$n],  // filename
                );
                $seen[] = $hash;
            }
        }
        $this->refreshTorrent($torrent);
        return $riplogList;
    }

    public function findById(Torrent|TorrentDeleted $torrent, int $id): ?\Gazelle\Riplog {
        $logId = self::$db->scalar("
            SELECT LogID FROM torrents_logs WHERE TorrentID = ? AND LogID = ?
            ", $torrent->id, $id
        );
        return is_null($logId) ? null : new \Gazelle\Riplog($torrent, (int)$logId);
    }

    public function fetchIdList(Torrent|TorrentDeleted $torrent): array {
        self::$db->prepared_query("
            SELECT LogID FROM torrents_logs WHERE TorrentID = ?
            ", $torrent->id
        );
        return self::$db->collect(0);
    }

    public function collection(Torrent|TorrentDeleted $torrent): RiplogCollection {
        return new RiplogCollection(
            array_map(
                fn ($id) => $this->findById($torrent, $id),
                $this->fetchIdList($torrent),
            )
        );
    }

    public function parse(
        string     $filepath,
        Logchecker $checker = new Logchecker(),
    ): Logchecker {
        $checker->newFile($filepath);
        $checker->parse();
        return $checker;
    }

    public function reparse(
        Torrent $torrent,
        int     $logId,
    ): int {
        $riplog = $this->findById($torrent, $logId);
        if (!$riplog) {
            return 0;
        }
        $parsed = $this->parse($riplog->file()->path());
        self::$db->prepared_query('
            UPDATE torrents_logs SET
                `Checksum`        = ?,
                ChecksumState     = ?,
                `Language`        = ?,
                Score             = ?,
                Details           = ?,
                Ripper            = ?,
                RipperVersion     = ?,
                LogcheckerVersion = ?
            WHERE TorrentID = ?
                AND LogID = ?
            ',  $parsed->getChecksumState() === Checksum::CHECKSUM_OK ? '1' : '0',
                $parsed->getChecksumState(),
                $parsed->getLanguage(),
                max(0, $parsed->getScore()),
                implode("\r\n", $parsed->getDetails()),
                $parsed->getRipper(),
                $parsed->getRipperVersion(),
                $this->logcheckerVersion(),
                $torrent->id,
                $logId,
        );

        $affectedRows = self::$db->affected_rows();
        if ($affectedRows > 0) {
            $riplog->html()->put($parsed->getLog());
            $this->refreshTorrent($torrent);
        }

        return $affectedRows;
    }

    public function logcheckerVersion(): string {
        return Logchecker::getLogcheckerVersion();
    }

    public function refreshTorrent(Torrent $torrent): int {
        $collection = $this->collection($torrent);
        return (int)$torrent->setField('HasLogDB', $collection->total() === 0 ? '0' : '1')
            ->setField('LogScore', $collection->overallScore())
            ->setField('LogChecksum', $collection->checksumStatus())
            ->modify();
    }

    /**
     * Remove all logfiles attached to this upload
     *
     * @return int number of logfiles removed
     */
    public function removeAll(Torrent $torrent, User $user): int {
        $affected = 0;
        self::$db->begin_transaction();
        foreach ($this->collection($torrent)->riplogList() as $riplog) {
            $affected += $riplog->remove();
        }
        $this->logger()->torrent($torrent, $user, "All logs removed from torrent");
        self::$db->commit();
        $this->refreshTorrent($torrent);
        return $affected; /** @phpstan-ignore-line return.type not it is not an int|float stupid bot */
    }
}
