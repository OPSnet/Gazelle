<?php

namespace GazelleUnitTest;

use Gazelle\Enum\UserStatus;
use Gazelle\ForumCategory;
use Gazelle\Manager\Category;
use Gazelle\Request\Encoding;
use Gazelle\Request\Format;
use Gazelle\Request\Media;
use Gazelle\Request\LogCue;
use Gazelle\TGroup;
use Gazelle\User;

class Helper {
    public static function makeForum(
        string                 $name,
        string                 $description,
        int                    $sequence,
        \Gazelle\ForumCategory $category,
        User                   $user,
        int                    $minClassRead   = 100,
        int                    $minClassWrite  = 100,
        int                    $minClassCreate = 100,
        bool                   $autoLock       = false,
        int                    $autoLockWeeks  = 42,
    ): \Gazelle\Forum {
        return new \Gazelle\Manager\Forum()->create(
            user:           $user,
            sequence:       $sequence,
            categoryId:     $category->id,
            name:           $name,
            description:    $description,
            minClassRead:   $minClassRead,
            minClassWrite:  $minClassWrite,
            minClassCreate: $minClassCreate,
            autoLock:       $autoLock,
            autoLockWeeks:  $autoLockWeeks,
        );
    }

    /**
     * @param array<int> $tagList
     */
    public static function makeRequestMusic(
        User     $user,
        string   $title,
        int      $releaseType     = 1,
        int      $year            = 2001,
        string   $description     = 'This is a unit test description',
        string   $image           = '',
        string   $recordLabel     = 'Unitest Artists',
        string   $catalogueNumber =  'UA-7890',
        array    $tagList         = [],
        Encoding $encoding        = new Encoding(list: ['Lossless', 'V0 (VBR)']),
        Format   $format          = new Format(list: ['MP3', 'FLAC']),
        Media    $media           = new Media(list: ['CD', 'WEB']),
        LogCue   $logCue          = new LogCue(
            needLogChecksum: true,
            needCue:         true,
            needLog:         true,
            minScore:        100,
        ),
    ): \Gazelle\Request {
        return new \Gazelle\Manager\Request()->create(
            user:            $user,
            bounty:          100 * 1024 * 1024,
            categoryId:      (int)new Category()->findIdByName('Music'),
            year:            $year,
            title:           $title,
            image:           $image,
            description:     $description,
            recordLabel:     $recordLabel,
            catalogueNumber: $catalogueNumber,
            releaseType:     $releaseType,
            encoding:        $encoding,
            format:          $format,
            media:           $media,
            logCue:          $logCue,
            tagList:         $tagList,
        );
    }

    /**
     * @param array<int> $tagList
     */
    public static function makeRequestComics(
        User   $user,
        string $title,
        string $description = 'This is a unit test description',
        array  $tagList     = [],
    ): \Gazelle\Request {
        return new \Gazelle\Manager\Request()->create(
            user:            $user,
            bounty:          100 * 1024 * 1024,
            categoryId:      (int)new Category()->findIdByName('Comics'),
            year:            (int)date('Y'),
            title:           $title,
            image:           null,
            description:     $description,
            recordLabel:     '',
            catalogueNumber: '',
            releaseType:     21,
            encoding:        new Encoding(list: ['Any']),
            format:          new Format(list: ['Any']),
            media:           new Media(list: ['Any']),
            logCue:          new LogCue(false, false, false, 0),
            tagList:         $tagList,
        );
    }

    public static function makeTGroupEBook(
        string $name,
    ): TGroup {
        return new \Gazelle\Manager\TGroup()->create(
            categoryId:      (int)new Category()->findIdByName('E-Books'),
            name:            $name,
            description:     'phpunit ebook description',
            image:           '',
            releaseType:     null,
            year:            null,
        );
    }

    public static function makeTGroupMusic(
        User    $user,
        string  $name,
        array   $artistName,
        array   $tagName,
        int     $releaseType = 1,
        ?int    $year        = null,
        ?string $image       = 'https://example.com/phpunit-tgroup/image.jpg',
    ): TGroup {
        $user->requestContext()->setViewer($user);
        $tgroup = new \Gazelle\Manager\TGroup()->create(
            categoryId:  (int)new Category()->findIdByName('Music'),
            releaseType: $releaseType,
            name:        $name,
            description: 'phpunit music description',
            image:       $image,
            year:        $year ?? (int)date('Y') - 1,
        );
        $tgroup->addArtists($artistName[0], $artistName[1]);
        $tagMan = new \Gazelle\Manager\Tag();
        foreach ($tagName as $name) {
            $tag = $tagMan->softCreate($name, $user);
            if ($tag) {
                $tag->addTGroup($tgroup, $user, 10);
            }
        }
        $tgroup->refresh();
        return $tgroup;
    }

    public static function makeTorrentEBook(
        TGroup $tgroup,
        User   $user,
        string $description,
    ): \Gazelle\Torrent {
        return new \Gazelle\Manager\Torrent()->create(
            tgroup:                  $tgroup,
            user:                    $user,
            description:             $description,
            media:                   'CD',
            format:                  null,
            encoding:                null,
            infohash:                'infohash-' . randomString(11),
            filePath:                'unit-test',
            fileList:                [],
            size:                    random_int(10_000_000, 99_999_999),
            isScene:                 false,
            remasterYear:            null,
            remasterTitle:           '',
            remasterRecordLabel:     '',
            remasterCatalogueNumber: '',
        );
    }

    public static function makeTorrentMusic(
        TGroup $tgroup,
        User   $user,
        string $media           = 'WEB',
        string $format          = 'FLAC',
        string $encoding        = 'Lossless',
        string $catalogueNumber = '',
        string $recordLabel     = 'Unitest Artists',
        string $title           = 'phpunit remaster title',
        int    $size            = 10_000_000,
        bool   $seed            = false,
    ): \Gazelle\Torrent {
        if (empty($catalogueNumber)) {
            $catalogueNumber = 'UA-REM-' . random_int(10000, 99999);
        }
        $torrent = new \Gazelle\Manager\Torrent()->create(
            tgroup:                  $tgroup,
            user:                    $user,
            description:             'phpunit release description',
            media:                   $media,
            format:                  $format,
            encoding:                $encoding,
            infohash:                'infohash-' . randomString(11),
            filePath:                'unit-test',
            fileList:                [],
            size:                    $size,
            isScene:                 false,
            remasterYear:            (int)date('Y'),
            remasterTitle:           $title,
            remasterRecordLabel:     $recordLabel,
            remasterCatalogueNumber: $catalogueNumber,
        );
        if ($seed) {
            static::generateTorrentSeed($torrent, $user);
        }
        return $torrent;
    }

    public static function addTorrentTraffic(
        \Gazelle\Torrent $torrent,
        int $leechTotal  = 0,
        int $seedTotal   = 0,
        int $snatchTotal = 0,
    ): int {
        $db = \Gazelle\DB::DB();
        $db->prepared_query("
            UPDATE torrents_leech_stats SET
                Leechers = ?,
                Seeders  = ?,
                Snatched = ?
            WHERE TorrentID = ?
            ", $leechTotal, $seedTotal, $snatchTotal, $torrent->id
        );
        return $db->affected_rows();
    }

    public static function generateTorrentSeed(
        \Gazelle\Torrent $torrent,
        User        $user,
        string      $ipAddr = '127.0.0.1',
        int         $interval = 5,
        string|null $useragent = null,
    ): int {
        if (is_null($useragent)) {
            $useragent = 'ua-' . randomString(12);
        }
        $db = \Gazelle\DB::DB();
        $db->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, ip, mtime, active, remaining, timespent)
            VALUES (?,   ?,   ?,         ?,       ?, unix_timestamp(now() - interval ? second), 1, 0, 1)
            ",  $torrent->id, $user->id, $useragent, randomString(20), $ipAddr, $interval
        );
        return $db->affected_rows();
    }

    public static function generateTorrentSnatch(\Gazelle\Torrent $torrent, User $user): int {
        $db = \Gazelle\DB::DB();
        $db->prepared_query("
            INSERT INTO xbt_snatched
                   (fid, uid, tstamp, IP, seedtime)
            VALUES (?,   ?,   unix_timestamp(now()), '127.0.0.1', 1)
            ", $torrent->id, $user->id
        );
        return $db->affected_rows();
    }

    public static function removeTGroup(TGroup $tgroup, User $user): void {
        $torMan = new \Gazelle\Manager\Torrent();
        if (!new \Gazelle\Manager\TGroup()->findById($tgroup->id)) {
            // Already deleted. This can occur when removing two
            // torrents separately that belong to the same group.
            // See TestContest for an example.
            return;
        }
        foreach ($tgroup->torrentIdList() as $torrentId) {
            $torMan->findById($torrentId)?->removeTorrent($user, 'phpunit teardown');
        }
        $tgroup->removeTGroup();
    }

    public static function makeUser(string $username, string $tag, bool $enable = false, bool $clearInbox = false): User {
        $user = new \Gazelle\UserCreator()
            ->setUsername($username)
            ->setEmail(randomString(6) . "@{$tag}.example.com")
            ->setPassword(randomString())
            ->addNote("Created by tests/helper/User($tag)")
            ->create();
        if ($enable) {
            $user->setField('Enabled', UserStatus::enabled->value)->modify();
        }
        if ($clearInbox) {
            $user = self::clearInbox($user);
        }
        return $user;
    }

    public static function makeUserByInvite(string $username, string $key): User {
        return new \Gazelle\UserCreator()
            ->setUsername($username)
            ->setEmail(randomString(6) . "@key.invite.example.com")
            ->setPassword(randomString())
            ->setInviteKey($key)
            ->addNote("Created by tests/helper/User(InviteKey)")
            ->create();
    }

    public static function clearInbox(User $user): User {
        $pmMan = new \Gazelle\Manager\PM($user);
        foreach ($user->inbox()->messageList($pmMan, 1, 0) as $pm) {
            $pm->remove();
        }
        return $user;
    }

    public static function removeUser(User $user): void {
        $tgMan = new \Gazelle\Manager\TGroup();
        foreach ($user->recentUploadList(100, true) as $tgroupId) {
            $tgroup = $tgMan->findById($tgroupId);
            if ($tgroup) {
                static::removeTGroup($tgroup, $user);
            }
        }
        $user->remove();
    }

    /**
     * Test whether a timestamp (YYYY-MM-DD HH:MM:SS) is close enough to now.
     * The default tolerance is 20 seconds.
     */
    public static function recentDate(string $date, int $tolerance = 20): bool {
        return self::recentEpoch(strtotime($date), $tolerance);
    }

    /**
     * Test whether a unix timestamp is close enough to now.
     * The default tolerance is 20 seconds.
     */
    public static function recentEpoch(int|false $epoch, int $tolerance = 20): bool {
        if ($epoch === false) {
            return false;
        }
        return time() - $epoch < $tolerance;
    }

    public static function recentDateTimeImmutable(\DateTimeImmutable $date, int $tolerance = 20): bool {
        return new \DateTimeImmutable('now')->getTimestamp() - $date->getTimestamp() < $tolerance;
    }

    /**
     * Test whether a timestamp (YYYY-MM-DD HH:MM:SS) in the future is close enough to now.
     * The default tolerance is 20 seconds.
     */
    public static function futureDate(string $date, int $tolerance = 20): bool {
        $epoch = strtotime($date);
        if ($epoch === false) {
            return false;
        }
        return $epoch - time() < $tolerance;
    }

    /* payments and donations can interfere with each other */
    public static function flushDonationMonth(int $month): void {
        global $Cache;
        $Cache->delete_value("donations_month_$month");
    }

    /**
     * Sleep long enough to allow the epoch second to roll over. This is needed
     * for Mysql timestamps, which have a resolution of one second, and Gazelle
     * requires certain events to have occurred "in the past". This avoids
     * having to sleep an entire second, when e.g. 2ms may suffice. On average,
     * the call duration will be 500ms.
     */
    public static function sleepTick(): float {
        $pause = (1 - (float)(explode(' ', microtime())[0]));
        usleep((int)(1000000 * $pause));
        return $pause;
    }
}
