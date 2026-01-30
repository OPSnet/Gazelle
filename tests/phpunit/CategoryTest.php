<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class CategoryTest extends TestCase {
    public function testCategory(): void {
        $manager = new Manager\Category();

        $this->assertEquals(1, $manager->findIdByName('Music'), 'cat-name-comics');
        $this->assertEquals(7, $manager->findIdByName('Comics'), 'cat-name-comics');
        $this->assertEquals('Comics', $manager->findNameById(7), 'cat-id-comics');
        $this->assertNull($manager->findIdByName('thereisnospoon'), 'cat-name-bogus');

        $this->assertEquals('Music', $manager->findNameById(1), 'cat-id-ebooks');
        $this->assertEquals('E-Books', $manager->findNameById(3), 'cat-id-ebooks');
        $this->assertNull($manager->findNameById(67890), 'cat-id-bogus');

        $categoryList = $manager->categoryList();
        $this->assertEquals('Global', $categoryList[0]['name'], 'cat-global');
    }

    public function testChangeCategory(): void {
        $tgMan  = new Manager\TGroup();
        $torMan = new Manager\Torrent();
        $user   = Helper::makeUser('tgcat.' . randomString(10), 'tgroup-cat');
        $user->requestContext()->setViewer($user);
        $tgroup = Helper::makeTGroupEBook(
            name: 'phpunit category change ' . randomString(6),
        );
        $this->assertFalse($tgroup->hasArtistRole(), 'tgroup-cat-non-music');
        $torrentList = array_map(fn($info) =>
            Helper::makeTorrentEBook(
                tgroup:      $tgroup,
                user:        $user,
                description: $info['description'],
            ), [
                ['description' => 'Full version'],
                ['description' => 'Abridged version'],
            ]
        );
        $idList = array_map(fn($t) => $t->id, $torrentList);

        // move one torrent to new category
        $artistName = 'phpunit chgcat ' . randomString(6);
        $new = $tgMan->changeCategory(
            old:         $tgroup,
            torrent:     $torrentList[1],
            categoryId:  (int)new Manager\Category()->findIdByName('Music'),
            name:        'phpunit category new ' . randomString(6),
            year:        (int)date('Y'),
            artistName:  $artistName,
            releaseType: new ReleaseType()->findIdByName('EP'),
            artistMan:   new Manager\Artist(),
            user:        $user,
        );
        $this->assertInstanceOf(TGroup::class, $new, 'cat-change-to-music');
        $this->assertTrue($new->hasArtistRole(), 'tgroup-cat-is-music');
        $artist = new Manager\Artist()->findByName($artistName);
        $this->assertInstanceOf(Artist::class, $artist, 'cat-new-artist-found');
        $this->assertEquals(
            [
                ARTIST_MAIN => [[
                    'id'      => $artist->id,
                    'name'    => $artist->name(),
                    'aliasid' => $artist->aliasId(),
                    'artist'  => $artist->flush(),
                ]],
            ],
            $new->artistRole()?->idList(),
            'cat-new-artist-role'
        );

        $tgroup->flush();

        // rebuild the torrent object caches
        $torrentList = array_map(fn ($id) => $torMan->findById($id), $idList);
        $this->assertInstanceOf(Torrent::class, $torrentList[0], 'cat-old-t0-found');
        $this->assertInstanceOf(Torrent::class, $torrentList[1], 'cat-old-t1-found');

        $this->assertEquals([$torrentList[0]->id], $tgroup->torrentIdList(), 'cat-old-tidlist');
        $this->assertEquals($torrentList[1]->groupId(), $new->id, 'cat-new-groupid');

        // move remaining torrent to same category
        $new = $tgMan->changeCategory(
            old:         $tgroup,
            torrent:     $torrentList[0],
            categoryId:  $tgroup->categoryId(), // same category as the original, null expected
            name:        'phpunit category new ' . randomString(6),
            year:        (int)date('Y'),
            artistName:  'phpunit chgcat ' . randomString(6),
            releaseType: new ReleaseType()->findIdByName('EP'),
            artistMan:   new Manager\Artist(),
            user:        $user,
        );
        $this->assertNull($new, 'cat-change-to-same');

        // move last torrent to new category, nuking old group
        $tgroupId = $tgroup->id;
        $new = $tgMan->changeCategory(
            old:         $tgroup,
            torrent:     $torrentList[0],
            categoryId:  (int)new Manager\Category()->findIdByName('Comedy'),
            name:        'phpunit category new ' . randomString(6),
            year:        (int)date('Y'),
            artistName:  null,
            releaseType: null,
            artistMan:   new Manager\Artist(),
            user:        $user,
        );
        $this->assertInstanceOf(TGroup::class, $new, 'cat-change-to-comedy');
        $this->assertNull($tgMan->findById($tgroupId), 'cat-old-tgroup-removed');

        $torrentList = array_map(fn($id) => $torMan->findById($id), $idList);

        // clean up
        foreach ($torrentList as $torrent) {
            $torrent?->removeTorrent($user, 'phpunit');
        }
        $tgroup->remove();
        $this->assertEquals(0, (int)DB::DB()->scalar("
            SELECT count(*) FROM torrents_artists WHERE GroupID = ?
            ", $tgroupId),
            'cat-old-group-no-artists'
        );
        $user->remove();
    }
}
