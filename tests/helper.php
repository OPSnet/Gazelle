<?php

class Helper {
    public static function makeTGroupMusic(
        \Gazelle\User $user,
        string $name,
        array $artistName,
        array $tagName,
        int $releaseType = 1
    ): \Gazelle\TGroup {
        $tgroup = (new \Gazelle\Manager\TGroup)->create(
            categoryId:      1,
            releaseType:     $releaseType,
            name:            $name,
            description:     'phpunit description',
            image:           '',
            year:            (int)date('Y') - 1,
            recordLabel:     'Unitest Artists Corporation',
            catalogueNumber: 'UA-' . random_int(10000, 99999),
            showcase:        false,
        );
        $tgroup->addArtists($user, $artistName[0], $artistName[1]);
        $tagMan = new \Gazelle\Manager\Tag;
        foreach ($tagName as $tag) {
            $tagMan->createTorrentTag($tagMan->create($tag, $user->id()), $tgroup->id(), $user->id(), 10);
        }
        $tgroup->refresh();
        return $tgroup;
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
}
