<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\Enum\RiplogChecksum;
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
        $id = $this->pg()->scalarex('
            insert into riplog
                   (id_torrent, checksum, score, riplog_language, ripper, logchecker_version, ripper_version, filename, details)
            values ($1,         $2,       $3,    $4,              $5,     $6,                 $7,             $8,       $9)
            returning id_riplog
            ', $torrent->id,
            match ($parsed->getChecksumState()) {
                Checksum::CHECKSUM_OK      => 'ok',
                Checksum::CHECKSUM_MISSING => 'missing',
                default                    => 'invalid',
            },
            max(0, $parsed->getScore()),
            $parsed->getLanguage(),
            $parsed->getRipper(),
            $this->logcheckerVersion(),
            $parsed->getRipperVersion(),
            $filename,
            $this->pg()->arrayConverter()->output($parsed->getDetails()),
        );
        $riplog = new \Gazelle\Riplog($torrent, $id);
        $riplog->file()->put($filepath);
        $riplog->html()->put($parsed->getLog());
        return $riplog;
    }

    public function bulkCreate(
        Torrent $torrent,
        array   $uploadList,
    ): array {
        $seen       = $this->collection($torrent)->riplogHashList();
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

    public function findById(Torrent|TorrentDeleted $torrent, int $id): \Gazelle\Riplog|null {
        $riplogId = $this->pg()->scalarex("
            select id_riplog from riplog where id_torrent = $1 and id_riplog = $2
            ", $torrent->id, $id
        );
        return is_null($riplogId) ? null : new \Gazelle\Riplog($torrent, (int)$riplogId);
    }

    public function fetchIdList(Torrent|TorrentDeleted $torrent): array {
        return $this->pg()->columnex('
            select id_riplog from riplog where id_torrent = $1
            ', $torrent->id
        );
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
        int     $riplogId, // int because we do not necessarily have an object on hand
    ): int {
        $riplog = $this->findById($torrent, $riplogId);
        if (!$riplog) {
            return 0;
        }
        $parsed = $this->parse($riplog->file()->path());
        $this->pg()->query('
            delete from riplog_adjust where id_riplog = $1
            ', $riplogId
        );
        $affected = $this->pg()->query('
            update riplog set
                checksum           = $1,
                score              = $2,
                riplog_language    = $3,
                ripper             = $4,
                logchecker_version = $5,
                ripper_version     = $6,
                details            = $7
            where id_riplog    = $8
                and id_torrent = $9
            ',
            match ($parsed->getChecksumState()) {
                Checksum::CHECKSUM_OK      => RiplogChecksum::ok,
                Checksum::CHECKSUM_MISSING => RiplogChecksum::missing,
                default                    => RiplogChecksum::invalid,
            },
            max(0, $parsed->getScore()),
            $parsed->getLanguage(),
            $parsed->getRipper(),
            $this->logcheckerVersion(),
            $parsed->getRipperVersion(),
            $this->pg()->arrayConverter()->output($parsed->getDetails()),
            $riplogId,
            $torrent->id,
        );
        if ($affected > 0) {
            $riplog->html()->put($parsed->getLog());
            $this->refreshTorrent($torrent);
        }
        return $affected;
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
        return $affected; /** @phpstan-ignore-line return.type no it is not an int|float stupid bot */
    }
}
