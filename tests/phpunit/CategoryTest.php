<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class CategoryTest extends TestCase {
    public function testCategory(): void {
        $manager = new Gazelle\Manager\Category;

        $this->assertEquals(1, $manager->findIdByName('Music'), 'cat-name-comics');
        $this->assertEquals(7, $manager->findIdByName('Comics'), 'cat-name-comics');
        $this->assertNull($manager->findIdByName('thereisnospoon'), 'cat-name-bogus');

        $this->assertEquals('Music', $manager->findNameById(1), 'cat-id-ebooks');
        $this->assertEquals('E-Books', $manager->findNameById(3), 'cat-id-ebooks');
        $this->assertNull($manager->findNameById(67890), 'cat-id-bogus');

        $categoryList = $manager->categoryList();
        $this->assertEquals('Global', $categoryList[0]['name'], 'cat-global');
    }

    public function testChangeCategory(): void {
        $tgMan  = new Gazelle\Manager\TGroup;
        $torMan = new Gazelle\Manager\Torrent;
        $user   = Helper::makeUser('tgcat.' . randomString(10), 'tgroup-cat');
        $tgroup = Helper::makeTGroupEBook(
            name: 'phpunit category change ' . randomString(6),
            user: $user,
        );
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
        $idList = array_map(fn($t) => $t->id(), $torrentList);

        // move one torrent to new category
        $artistName = 'new artist ' . randomString(6);
        $new = $tgMan->changeCategory(
            old:         $tgroup,
            torrent:     $torrentList[1],
            categoryId:  (new Gazelle\Manager\Category)->findIdByName('Music'),
            name:        'phpunit category new ' . randomString(6),
            year:        (int)date('Y'),
            artistName:  $artistName,
            releaseType: (new Gazelle\ReleaseType)->findIdByName('EP'),
            artistMan:   new Gazelle\Manager\Artist,
            logger:      new Gazelle\Log,
            user:        $user,
        );
        $this->assertInstanceOf(Gazelle\TGroup::class, $new, 'cat-change-to-music');
        $artist = (new Gazelle\Manager\Artist)->findByName($artistName);
        $this->assertEquals($artistName, $artist->name(), 'cat-new-artist');
        $this->assertEquals(
            [
                ARTIST_MAIN => [
                    ['id' => $artist->id(), 'name' => $artist->name()],
                ],
            ],
            $new->artistRole()->idList(),
            'cat-new-artist-role'
        );

        $tgroup->flush();

        // rebuild the torrent object caches
        $torrentList = array_map(fn($id) => $torMan->findById($id), $idList);

        $this->assertEquals([$torrentList[0]->id()], $tgroup->torrentIdList(), 'cat-old-tidlist');
        $this->assertEquals($torrentList[1]->groupId(), $new->id(), 'cat-new-groupid');

        // move remaining torrent to same category
        $new = $tgMan->changeCategory(
            old:         $tgroup,
            torrent:     $torrentList[0],
            categoryId:  $tgroup->categoryId(), // same category as the original, null expected
            name:        'phpunit category new ' . randomString(6),
            year:        (int)date('Y'),
            artistName:  'new artist ' . randomString(6),
            releaseType: (new Gazelle\ReleaseType)->findIdByName('EP'),
            artistMan:   new Gazelle\Manager\Artist,
            logger:      new Gazelle\Log,
            user:        $user,
        );
        $this->assertNull($new, 'cat-change-to-same');

        // move last torrent to new category, nuking old group
        $tgroupId = $tgroup->id();
        $new = $tgMan->changeCategory(
            old:         $tgroup,
            torrent:     $torrentList[0],
            categoryId:  (new Gazelle\Manager\Category)->findIdByName('Comedy'),
            name:        'phpunit category new ' . randomString(6),
            year:        (int)date('Y'),
            artistName:  null,
            releaseType: null,
            artistMan:   new Gazelle\Manager\Artist,
            logger:      new Gazelle\Log,
            user:        $user,
        );
        $this->assertInstanceOf(Gazelle\TGroup::class, $new, 'cat-change-to-comedy');
        $this->assertNull($tgMan->findById($tgroupId), 'cat-old-tgroup-removed');

        $torrentList = array_map(fn($id) => $torMan->findById($id), $idList);

        // clean up
        foreach ($torrentList as $torrent) {
            $torrent->remove($user, 'phpunit');
        }
        $tgroup->remove($user);
        $this->assertEquals(0, (int)Gazelle\DB::DB()->scalar("
            SELECT count(*) FROM torrents_artists WHERE GroupID = ?
            ", $tgroupId),
            'cat-old-group-no-artists'
        );
        $user->remove();
    }
}
