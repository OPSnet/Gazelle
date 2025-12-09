<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\FeaturedAlbumType;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class FeaturedAlbumTest extends TestCase {
    use Pg;

    protected TGroup $tgroup;
    protected User   $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('feat.' . randomString(10), 'featured');
        $this->user->requestContext()->setViewer($this->user);
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit feat ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['DJ Feature ' . randomString(12)]],
            tagName:    ['opera'],
            user:       $this->user,
        );
    }

    public function tearDown(): void {
        new Manager\News()->remove(
            (int)$this->pg()->scalar("
                select id_news from news where id_user = ?
                ", $this->user->id
            )
        );
        new Manager\FeaturedAlbum()->findById($this->tgroup->id)?->remove();
        Helper::removeUser($this->user);
    }

    public function testFeaturedAotm(): void {
        $manager = new Manager\FeaturedAlbum();
        $aotm = $manager->create(
            featureType: FeaturedAlbumType::AlbumOfTheMonth,
            news:        new Manager\News(),
            tgMan:       new Manager\TGroup(),
            threadMan:   new Manager\ForumThread(),
            torMan:      new Manager\Torrent(),
            tracker:     new Tracker(),
            forum:       new Forum(AOTM_FORUM_ID),
            tgroup:      $this->tgroup,
            title:       'AOTM phpunit feature ' . date('Y-m-d H:i:s'),
            body:        'this is an aotm body',
            pitch:       'phpunit discuss aotm',
            user:        $this->user,
            leechType:   LeechType::Free,
            threshold:   20000,
        );
        $this->assertEquals($this->tgroup->id, $aotm->tgroupId(), 'aotm-tgroupid');
        $this->assertEquals($this->tgroup->id, $aotm->tgroup()->id, 'aotm-tgroup-id');

        $find = $manager->findByType(FeaturedAlbumType::AlbumOfTheMonth);
        $this->assertInstanceOf(FeaturedAlbum::class, $find, 'aotm-found-by-type');
        $this->assertEquals($aotm->id, $find->id, 'aotm-find-by-type');

        $find = $manager->findById($this->tgroup->id);
        $this->assertInstanceOf(FeaturedAlbum::class, $find, 'aotm-found-by-id');
        $this->assertEquals($aotm->id, $find->id, 'aotm-find-by-id');
        $this->assertEquals(FeaturedAlbumType::AlbumOfTheMonth, $find->type(), 'aotm-type');

        $this->assertEquals(1, $aotm->unfeature(), 'aotm-unfeature');
        $this->assertNull($manager->findByType(FeaturedAlbumType::AlbumOfTheMonth), 'aotm-no-featured');
    }

    public function testFeaturedShowcase(): void {
        $manager = new Manager\FeaturedAlbum();
        $showcase = $manager->create(
            featureType: FeaturedAlbumType::Showcase,
            news:        new Manager\News(),
            tgMan:       new Manager\TGroup(),
            threadMan:   new Manager\ForumThread(),
            torMan:      new Manager\Torrent(),
            tracker:     new Tracker(),
            forum:       new Forum(AOTM_FORUM_ID),
            tgroup:      $this->tgroup,
            title:       'Showcase phpunit feature ' . date('Y-m-d H:i:s'),
            body:        'this is a showcase body',
            pitch:       'phpunit discuss showcase',
            user:        $this->user,
            leechType:   LeechType::Free,
            threshold:   20000,
        );
        $this->assertEquals($this->tgroup->id, $showcase->tgroupId(), 'showcase-tgroupid');

        $find = $manager->findByType(FeaturedAlbumType::Showcase);
        $this->assertInstanceOf(FeaturedAlbum::class, $find, 'showcase-found-by-type');
        $this->assertEquals($showcase->id, $find->id, 'showcase-find-by-type');

        $find = $manager->findById($this->tgroup->id);
        $this->assertInstanceOf(FeaturedAlbum::class, $find, 'showcase-found-by-id');
        $this->assertEquals($showcase->id, $find->id, 'showcase-find-by-id');
        $this->assertEquals(FeaturedAlbumType::Showcase, $find->type(), 'showcase-type');

        $this->assertEquals(1, $showcase->unfeature(), 'aotm-unfeature');
        $this->assertNull($manager->findByType(FeaturedAlbumType::AlbumOfTheMonth), 'aotm-no-featured');
    }
}
