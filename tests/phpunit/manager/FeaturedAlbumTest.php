<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

use Gazelle\Enum\FeaturedAlbumType;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class FeaturedAlbumTest extends TestCase {
    protected Gazelle\TGroup      $tgroup;
    protected Gazelle\ForumThread $thread;
    protected Gazelle\User        $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('feat.' . randomString(10), 'featured');
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit feat ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['DJ Feature ' . randomString(12)]],
            tagName:    ['opera'],
            user:       $this->user,
        );
        $this->thread =
            (new \Gazelle\Manager\ForumThread)->create(
                new Gazelle\Forum(AOTM_FORUM_ID),
                $this->user->id(),
                'phpunit aotm ' . $this->tgroup->text(),
                'this is an aotm thread'
            );
    }

    public function tearDown(): void {
        $db = \Gazelle\DB::DB();
        (new Gazelle\Manager\News)->remove(
            (int)$db->scalar("
                SELECT ID FROM news WHERE UserID = ?
                ", $this->user->id()
            )
        );
        (new Gazelle\Manager\FeaturedAlbum)->findById($this->tgroup->id())?->remove();
        $this->tgroup->remove($this->user);
        $this->thread->remove();
        $this->user->remove();
    }

    public function testFeaturedAotm(): void {
        $manager = new Gazelle\Manager\FeaturedAlbum;
        $aotm = $manager->create(
            featureType: FeaturedAlbumType::AlbumOfTheMonth,
            news:        new \Gazelle\Manager\News,
            tgMan:       new \Gazelle\Manager\TGroup,
            torMan:      new \Gazelle\Manager\Torrent,
            tracker:     new \Gazelle\Tracker,
            tgroup:      $this->tgroup,
            forumThread: $this->thread,
            title:       'AOTM phpunit feature ' . date('Y-m-d H:i:s'),
            user:        $this->user,
            leechType:   LeechType::Free,
            threshold:   20000,
        );
        $this->assertEquals($this->thread->id(), $aotm->thread()->id(), 'aotm-thread-id');
        $this->assertEquals($this->thread->link(), $aotm->link(), 'aotm-link');
        $this->assertEquals($this->thread->location(), $aotm->location(), 'aotm-location');
        $this->assertEquals($this->thread->title(), $aotm->thread()->title(), 'aotm-thread-title');
        $this->assertEquals($this->tgroup->id(), $aotm->tgroupId(), 'aotm-tgroupid');
        $this->assertEquals($this->tgroup->id(), $aotm->tgroup()->id(), 'aotm-tgroup-id');

        $find = $manager->findByType(FeaturedAlbumType::AlbumOfTheMonth);
        $this->assertEquals($aotm->id(), $find->id(), 'aotm-find-by-type');

        $find = $manager->findById($this->tgroup->id());
        $this->assertEquals($aotm->id(), $find->id(), 'aotm-find-by-id');
        $this->assertEquals(FeaturedAlbumType::AlbumOfTheMonth, $find->type(), 'aotm-type');

        $this->assertEquals(1, $aotm->unfeature(), 'aotm-unfeature');
        $this->assertNull($manager->findByType(FeaturedAlbumType::AlbumOfTheMonth), 'aotm-no-featured');
    }

    public function testFeaturedShowcase(): void {
        $manager = new Gazelle\Manager\FeaturedAlbum;
        $showcase = $manager->create(
            featureType: FeaturedAlbumType::Showcase,
            news:        new \Gazelle\Manager\News,
            tgMan:       new \Gazelle\Manager\TGroup,
            torMan:      new \Gazelle\Manager\Torrent,
            tracker:     new \Gazelle\Tracker,
            tgroup:      $this->tgroup,
            forumThread: $this->thread,
            title:       'Showcase phpunit feature ' . date('Y-m-d H:i:s'),
            user:        $this->user,
            leechType:   LeechType::Free,
            threshold:   20000,
        );
        $this->assertEquals($this->thread->id(), $showcase->thread()->id(), 'showcase-thread-id');
        $this->assertEquals($this->tgroup->id(), $showcase->tgroupId(), 'showcase-tgroupid');

        $find = $manager->findByType(FeaturedAlbumType::Showcase);
        $this->assertEquals($showcase->id(), $find->id(), 'showcase-find-by-type');

        $find = $manager->findById($this->tgroup->id());
        $this->assertEquals($showcase->id(), $find->id(), 'showcase-find-by-id');
        $this->assertEquals(FeaturedAlbumType::Showcase, $find->type(), 'showcase-type');

        $this->assertEquals(1, $showcase->unfeature(), 'aotm-unfeature');
        $this->assertNull($manager->findByType(FeaturedAlbumType::AlbumOfTheMonth), 'aotm-no-featured');
    }
}
