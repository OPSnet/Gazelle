<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class CollageFreeleechTest extends TestCase {
    protected Collage $collage;
    protected User    $user;
    protected array            $tgroupList;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('collfree.' . randomString(10), 'collage.manager');
        $this->tgroupList = [
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'phpunit collfree ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['Dr Collfree ' . randomString(12)]],
                tagName:    ['hip.hop'],
                user:       $this->user,
            ),
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'phpunit collfree ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['Dr Collfree ' . randomString(12)]],
                tagName:    ['hip.hop'],
                user:       $this->user,
            ),
        ];
        $this->collage = (new Manager\Collage())->create(
            user:        $this->user,
            categoryId:  2,
            name:        'phpunit collfree ' . randomString(10),
            description: 'phpunit collfree',
            tagList:     'pop',
        );
        foreach ($this->tgroupList as $tgroup) {
            foreach (
                [
                    ['format' => 'FLAC', 'size' => 10_000_000],
                    ['format' => 'FLAC', 'size' => 15_000_000],
                    ['format' => 'MP3',  'size' =>  2_000_000],
                ] as $info
            ) {
                $torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
                    tgroup: $tgroup,
                    format: $info['format'],
                    size:   $info['size'],
                    user:   $this->user,
                );
            }
            $this->collage->addEntry($tgroup->flush(), $this->user);
        }
    }

    public function tearDown(): void {
        $torMan = new Manager\Torrent();
        foreach ($this->tgroupList as $tgroup) {
            $this->collage->removeEntry($tgroup);
            foreach ($tgroup->torrentIdList() as $torrentId) {
                $torMan->findById($torrentId)->remove($this->user, 'collfree unit test');
            }
            $tgroup->remove($this->user);
        }
        $this->collage->hardRemove();
        $this->user->remove();
    }

    public function testCollageFreeleech(): void {
        $torMan = new Manager\Torrent();
        $idList = $this->collage->entryFlacList();
        $this->assertCount(4, $idList, 'collfree-flac-list');
        $this->assertEquals(
            4,
            $this->collage->setFreeleech(
                torMan:    $torMan,
                tracker:   new Tracker(),
                user:      $this->user,
                leechType: LeechType::Free,
                reason:    LeechReason::Permanent,
                threshold: 12_000_000,
            ),
            'collfree-free'
        );
        $n = 0;
        foreach ($this->tgroupList as $tgroup) {
            ++$n;
            $idList = $tgroup->torrentIdList();
            sort($idList);
            $torrentList = array_map(fn($id) => $torMan->findById($id)->flush(), $idList);
            $this->assertEquals(LeechType::Free, $torrentList[0]->leechType(), "collfree-t0-free-$n");
            $this->assertEquals(LeechType::Neutral, $torrentList[1]->leechType(), "collfree-t1-neutral-$n");
            $this->assertEquals(LeechType::Normal, $torrentList[2]->leechType(), "collfree-t2-normal-$n");
        }

        $this->assertEquals(
            4,
            $this->collage->setFreeleech(
                torMan:    $torMan,
                tracker:   new Tracker(),
                user:      $this->user,
                leechType: LeechType::Normal,
                reason:    LeechReason::Normal,
            ),
            'collfree-normal'
        );
        $n = 0;
        foreach ($this->tgroupList as $tgroup) {
            foreach (array_map(fn($id) => $torMan->findById($id)->flush(), $tgroup->torrentIdList()) as $torrent) {
                ++$n;
                $this->assertEquals(LeechType::Normal, $torrent->leechType(), "collfree-now-normal-$n");
            }
        }
    }
}
