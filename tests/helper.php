<?php

class Helper {
    public static function makeTGroupEBook(
        string        $name,
        \Gazelle\User $user,
    ): \Gazelle\TGroup {
        return (new \Gazelle\Manager\TGroup)->create(
            categoryId:      (new \Gazelle\Manager\Category)->findIdByName('E-Books'),
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
        $tgroup = (new \Gazelle\Manager\TGroup)->create(
            categoryId:      (new \Gazelle\Manager\Category)->findIdByName('Music'),
            releaseType:     $releaseType,
            name:            $name,
            description:     'phpunit music description',
            image:           '',
            year:            (int)date('Y') - 1,
            recordLabel:     'Unitest Artists Corporation',
            catalogueNumber: 'UA-' . random_int(10000, 99999),
        );
        $tgroup->addArtists($artistName[0], $artistName[1], $user, new Gazelle\Manager\Artist, new Gazelle\Log);
        $tagMan = new \Gazelle\Manager\Tag;
        foreach ($tagName as $tag) {
            $tagMan->createTorrentTag($tagMan->create($tag, $user->id()), $tgroup->id(), $user->id(), 10);
        }
        $tgroup->refresh();
        return $tgroup;
    }

    public static function makeTorrentEBook(
        int           $tgroupId,
        string        $description,
        \Gazelle\User $user,
    ): \Gazelle\Torrent {
        return (new \Gazelle\Manager\Torrent)->create(
            tgroupId:                $tgroupId,
            userId:                  $user->id(),
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
        int $tgroupId,
        \Gazelle\User $user,
        string $media       = 'WEB',
        string $format      = 'FLAC',
        string $encoding    = 'Lossless',
        string $title       = '',
    ): \Gazelle\Torrent {
        return (new \Gazelle\Manager\Torrent)->create(
            tgroupId:                $tgroupId,
            userId:                  $user->id(),
            description:             'phpunit release description',
            media:                   $media,
            format:                  $format,
            encoding:                $encoding,
            infohash:                'infohash-' . randomString(10),
            filePath:                'unit-test',
            fileList:                [],
            size:                    random_int(10_000_000, 99_999_999),
            isScene:                 false,
            isRemaster:              true,
            remasterYear:            (int)date('Y'),
            remasterTitle:           $title,
            remasterRecordLabel:     'Unitest Artists',
            remasterCatalogueNumber: 'UA-REM-' . random_int(10000, 99999),
        );
    }

    public static function makeUser(string $username, string $tag): \Gazelle\User {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        return (new Gazelle\UserCreator)
            ->setUsername($username)
            ->setEmail(randomString(6) . "@{$tag}.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setAdminComment("Created by tests/helper/User($tag)")
            ->create();
    }

    public static function makeUserByInvite(string $username, string $key): \Gazelle\User {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        return (new Gazelle\UserCreator)
            ->setUsername($username)
            ->setEmail(randomString(6) . "@key.invite.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setInviteKey($key)
            ->setAdminComment("Created by tests/helper/User(InviteKey)")
            ->create();
    }

    public static function modifyUserAvatar(\Gazelle\User $user, string $url): int {
        $db = Gazelle\DB::DB();
        $db->prepared_query("
            UPDATE users_info SET Avatar = ? WHERE UserID = ?
            ", $url, $user->id()
        );
        $affected = $db->affected_rows();
        $user->flush();
        return $affected;
    }
}
