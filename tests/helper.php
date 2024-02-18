<?php

use Gazelle\Enum\UserStatus;

class Helper {
    public static function makeForum(
        string                 $name,
        string                 $description,
        int                    $sequence,
        \Gazelle\ForumCategory $category,
        \Gazelle\User          $user,
        int                    $minClassRead   = 100,
        int                    $minClassWrite  = 100,
        int                    $minClassCreate = 100,
        bool                   $autoLock       = false,
        int                    $autoLockWeeks  = 42,
    ): \Gazelle\Forum {
        return (new Gazelle\Manager\Forum())->create(
            user:           $user,
            sequence:       $sequence,
            categoryId:     $category->id(),
            name:           $name,
            description:    $description,
            minClassRead:   $minClassRead,
            minClassWrite:  $minClassWrite,
            minClassCreate: $minClassCreate,
            autoLock:       $autoLock,
            autoLockWeeks:  $autoLockWeeks,
        );
    }

    public static function makeRequestMusic(
        \Gazelle\User $user,
        string        $title,
        int           $releaseType     = 1,
        string        $description     = 'This is a unit test description',
        string        $recordLabel     = 'Unitest Artists',
        string        $catalogueNumber =  'UA-7890',
        string        $encodingList    = 'Lossless|V0 (VBR)',
        string        $formatList      = 'MP3|FLAC',
        string        $mediaList       = 'CD|WEB',
        string        $logCue          = 'Log (100%) + Cue',
        bool          $checksum        = true,
    ): \Gazelle\Request {
        return (new \Gazelle\Manager\Request())->create(
            user:            $user,
            bounty:          100 * 1024 ** 3,
            categoryId:      (new \Gazelle\Manager\Category())->findIdByName('Music'),
            year:            (int)date('Y'),
            title:           $title,
            image:           '',
            description:     $description,
            recordLabel:     $recordLabel,
            catalogueNumber: $catalogueNumber,
            releaseType:     $releaseType,
            encodingList:    $encodingList,
            formatList:      $formatList,
            mediaList:       $mediaList,
            logCue:          $logCue,
            checksum:        $checksum,
            oclc:            '123,456',
        );
    }

    public static function makeTGroupEBook(
        string        $name,
        \Gazelle\User $user,
    ): \Gazelle\TGroup {
        return (new \Gazelle\Manager\TGroup())->create(
            categoryId:      (new \Gazelle\Manager\Category())->findIdByName('E-Books'),
            name:            $name,
            description:     'phpunit ebook description',
            image:           '',
            recordLabel:     '',
            catalogueNumber: '',
            releaseType:     null,
            year:            null,
        );
    }

    public static function makeTGroupMusic(
        \Gazelle\User $user,
        string $name,
        array $artistName,
        array $tagName,
        int $releaseType = 1
    ): \Gazelle\TGroup {
        $tgroup = (new \Gazelle\Manager\TGroup())->create(
            categoryId:      (new \Gazelle\Manager\Category())->findIdByName('Music'),
            releaseType:     $releaseType,
            name:            $name,
            description:     'phpunit music description',
            image:           'https://example.com/phpunit-tgroup/' . randomString(10) . '.jpg',
            year:            (int)date('Y') - 1,
            recordLabel:     'Unitest Artists Corporation',
            catalogueNumber: 'UA-' . random_int(10000, 99999),
        );
        $tgroup->addArtists($artistName[0], $artistName[1], $user, new Gazelle\Manager\Artist(), new Gazelle\Log());
        $tagMan = new \Gazelle\Manager\Tag();
        foreach ($tagName as $tag) {
            $tagMan->createTorrentTag($tagMan->create($tag, $user), $tgroup, $user, 10);
        }
        $tgroup->refresh();
        return $tgroup;
    }

    public static function makeTorrentEBook(
        \Gazelle\TGroup $tgroup,
        \Gazelle\User   $user,
        string          $description,
    ): \Gazelle\Torrent {
        return (new \Gazelle\Manager\Torrent())->create(
            tgroup:                  $tgroup,
            user:                    $user,
            description:             $description,
            media:                   'CD',
            format:                  null,
            encoding:                null,
            infohash:                'infohash-' . randomString(10),
            filePath:                'unit-test',
            fileList:                [],
            size:                    random_int(10_000_000, 99_999_999),
            isScene:                 false,
            isRemaster:              false,
            remasterYear:            null,
            remasterTitle:           '',
            remasterRecordLabel:     '',
            remasterCatalogueNumber: '',
        );
    }

    public static function makeTorrentMusic(
        \Gazelle\TGroup $tgroup,
        \Gazelle\User   $user,
        string          $media           = 'WEB',
        string          $format          = 'FLAC',
        string          $encoding        = 'Lossless',
        string          $catalogueNumber = '',
        string          $recordLabel     = 'Unitest Artists',
        string          $title           = 'phpunit remaster title',
        int             $size            = 10_000_000,
    ): \Gazelle\Torrent {
        if (empty($catalogueNumber)) {
            $catalogueNumber = 'UA-REM-' . random_int(10000, 99999);
        }
        return (new \Gazelle\Manager\Torrent())->create(
            tgroup:                  $tgroup,
            user:                    $user,
            description:             'phpunit release description',
            media:                   $media,
            format:                  $format,
            encoding:                $encoding,
            infohash:                'infohash-' . randomString(10),
            filePath:                'unit-test',
            fileList:                [],
            size:                    $size,
            isScene:                 false,
            isRemaster:              true,
            remasterYear:            (int)date('Y'),
            remasterTitle:           $title,
            remasterRecordLabel:     $recordLabel,
            remasterCatalogueNumber: $catalogueNumber,
        );
    }

    public static function makeUser(string $username, string $tag, bool $enable = false, bool $clearInbox = false): \Gazelle\User {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $user = (new Gazelle\UserCreator())
            ->setUsername($username)
            ->setEmail(randomString(6) . "@{$tag}.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setAdminComment("Created by tests/helper/User($tag)")
            ->create();
        if ($enable) {
            $user->setField('Enabled', UserStatus::enabled->value)->modify();
        }
        if ($clearInbox) {
            $user = self::clearInbox($user);
        }
        return $user;
    }

    public static function clearInbox(\Gazelle\User $user): \Gazelle\User {
        $pmMan = new \Gazelle\Manager\PM($user);
        foreach ($user->inbox()->messageList($pmMan, 1, 0) as $pm) {
            $pm->remove();
        }
        return $user;
    }

    public static function makeUserByInvite(string $username, string $key): \Gazelle\User {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        return (new Gazelle\UserCreator())
            ->setUsername($username)
            ->setEmail(randomString(6) . "@key.invite.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setInviteKey($key)
            ->setAdminComment("Created by tests/helper/User(InviteKey)")
            ->create();
    }

    public static function removeTGroup(\Gazelle\TGroup $tgroup, \Gazelle\User $user): void {
        $torMan = new \Gazelle\Manager\Torrent();
        foreach ($tgroup->torrentIdList() as $torrentId) {
            $torMan->findById($torrentId)?->remove($user, 'phpunit teardown');
        }
        $tgroup->remove($user);
    }

    /**
     * Test whether a timestamp (YYYY-MM-DD HH:MM:SS) is close enough to now.
     * The default tolerance is 20 seconds.
     */
    public static function recentDate(string $date, int $tolerance = 20): bool {
        $epoch = strtotime($date);
        if ($epoch === false) {
            return false;
        }
        return time() - $epoch < $tolerance;
    }
}
