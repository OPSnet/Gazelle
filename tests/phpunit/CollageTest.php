<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\CollageType;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

/**
 * Note: these tests are not interested in how a collage was acquired.
 * Look at, for instance, the tests for donors and bonus points for that.
 */


class CollageTest extends TestCase {
    protected array $collageList;
    protected array $artistName;
    protected array $tgroupList;
    protected array $userList;

    protected function tagList(int $n): array {
        $tagList = explode(' ', 'acoustic jazz metal rock trance');
        return array_slice($tagList, 0, $n);
    }

    public function setUp(): void {
        $this->userList = [
            'u1'  => Helper::makeUser('u1.' . randomString(6), 'collage', clearInbox: true),
            'u2'  => Helper::makeUser('u2.' . randomString(6), 'collage', clearInbox: true),
            'u3'  => Helper::makeUser('u3.' . randomString(6), 'collage', clearInbox: true),
        ];
        $this->artistName = [
            'The phpunit ' . randomString(8) . ' Band',
            'The phpunit ' . randomString(8) . ' Sisters',
            'The phpunit ' . randomString(8) . ' Brothers',
            'The phpunit ' . randomString(8) . ' Mothers',
            'The phpunit ' . randomString(8) . ' Fathers',
        ];

        $this->tgroupList = [];
        $artistMan = new Gazelle\Manager\Artist;
        $log       = new Gazelle\Log;
        $user      = $this->userList['u1'];
        $this->tgroupList = [
            Helper::makeTGroupMusic(
                $user,
                'Some ' . randomString(8) . ' songs',
                [[ARTIST_MAIN], [$this->artistName[0]]],
                $this->tagList(1),
            ),
            Helper::makeTGroupMusic(
                $user,
                'Some ' . randomString(8) . ' songs',
                [[ARTIST_MAIN], [$this->artistName[1]]],
                $this->tagList(2),
            ),
            Helper::makeTGroupMusic(
                $user,
                'Some ' . randomString(8) . ' songs',
                [[ARTIST_MAIN], [$this->artistName[2], $this->artistName[3]]],
                $this->tagList(3),
            ),
            Helper::makeTGroupMusic(
                $user,
                'Some ' . randomString(8) . ' songs',
                [[ARTIST_MAIN], [$this->artistName[3]]],
                $this->tagList(4),
            ),
            Helper::makeTGroupMusic(
                $user,
                'Some ' . randomString(8) . ' songs',
                [[ARTIST_MAIN], [$this->artistName[4]]],
                $this->tagList(5),
            ),
        ];
    }

    public function tearDown(): void {
        foreach ($this->collageList as $collage) {
            $collage->toggleAttr('sort-newest', false);
            $collage->hardRemove();
        }
        foreach ($this->tgroupList as $tgroup) {
            $tgroup->remove($this->userList['u1']);
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testArgParse(): void {
        $this->collageList[] = (new Gazelle\Manager\Collage)->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::theme->value,
            name:        'phpunit collage lock ' . randomString(20),
            description: 'phpunit collage lock description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];
        // NB: These objects should never be instantiated directly
        // We only do so here in order to test them.
        $inner = new Gazelle\Collage\TGroup($collage);

        $this->assertEquals(
            [],
            $inner->parseUrlArgs('', 'li[]'),'collage-parse-no-arg'
        );
        $this->assertEquals(
            [],
            $inner->parseUrlArgs('y=1&y=2&y=3', 'x'), 'collage-parse-no-match'
        );
        $this->assertEquals(
            [33, 44],
            $inner->parseUrlArgs('a=11&b=33&a=22&b=44', 'b'), 'collage-parse-some'
        );
        $this->assertEquals(
            [14, 31, 58, 68, 69, 54, 5],
            $inner->parseUrlArgs(
                'li[]=14&li[]=31&li[]=58&li[]=68&li[]=69&li[]=54&li[]=5',
                'li[]'
            ),
            'collage-parse-args'
        );
    }

    public function test00CollageCreate(): void {
        $manager     = new Gazelle\Manager\Collage;
        $stats       = new Gazelle\Stats\Collage;
        $total       = $stats->collageTotal();

        $name        = 'phpunit collage create ' . randomString(20);
        $description = 'phpunit collage create description';
        $tagList     = $this->tagList(3);
        $this->collageList[] = $manager->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::theme->value,
            name:        $name,
            description: $description,
            tagList:     implode(' ', $tagList),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];

        $this->assertEquals($total + 1, $stats->collageTotal(), 'collage-stats-total');
        $this->assertEquals($total + 2, $stats->increment(), 'collage-stats-increment');
        $this->assertEquals($collage->id(), $manager->findById($collage->id())?->id(), 'collage-find-by-id');
        $this->assertEquals(0, $collage->maxGroups(), 'collage-max-group');
        $this->assertEquals(0, $collage->maxGroupsPerUser(), 'collage-max-per-user');
        $this->assertEquals(0, $collage->numEntries(), 'collage-num-subcribers');
        $this->assertEquals(1, $collage->categoryId(), 'collage-id');
        $this->assertEquals($description, $collage->description(), 'collage-description');
        $this->assertEquals($name, $collage->name(), 'collage-name');
        $this->assertEquals($this->userList['u1']->id(), $collage->ownerId(), 'collage-owner-id');
        $this->assertEquals($tagList, $collage->tags(), 'collage-tag-list');
        $this->assertFalse($collage->sortNewest(), 'collage-sort-initial');
        $this->assertFalse($collage->isArtist(), 'collage-is-not-artist');
        $this->assertFalse($collage->isDeleted(), 'collage-is-not-deleted');
        $this->assertFalse($collage->isFeatured(), 'collage-is-not-featured');
        $this->assertFalse($collage->isOwner($this->userList['u2']->id()), 'collage-is-not-owner');
        $this->assertTrue($collage->isOwner($this->userList['u1']->id()), 'collage-is-owner');
        $this->assertStringContainsString($collage->name(), $collage->link(), 'collage-link');
        $this->assertFalse($collage->hasAttr('sort-newest'), 'collage-no-sort-newest');

        $find = $manager->findByName($name);
        $this->assertEquals($collage->id(), $find->id(), 'collage-find-by-name');

        $this->userList['u1']->addCustomPrivilege('site_collages_manage');
    }

    public function testCollageAdd(): void {
        $manager = new Gazelle\Manager\Collage;
        $this->collageList[] = $manager->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::theme->value,
            name:        'phpunit collage add ' . randomString(20),
            description: 'phpunit collage add description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];

        $this->assertEquals(0, $collage->numSubscribers(), 'collage-num-subcribers');
        $this->assertFalse($collage->isSubscribed($this->userList['u2']), 'collage-is-not-subcribed');
        $this->assertEquals(1, $collage->toggleSubscription($this->userList['u2']), 'collage-subcribe');
        $this->assertTrue($collage->isSubscribed($this->userList['u2']), 'collage-is-subcribed');
        $this->assertEquals(1, $collage->numSubscribers(), 'collage-one-subcriber');

        $this->assertFalse($collage->isPersonal(), 'collage-is-not-personal');
        $this->assertFalse($collage->userCanContribute($this->userList['u3']), 'collage-cannot-contribute');
        $this->assertTrue($this->userList['u3']->addCustomPrivilege('site_collages_manage'), 'user-can-collaborate');
        $this->assertTrue($this->userList['u3']->permitted('site_collages_manage'), 'user-can-manage-collage');
        $this->assertTrue($collage->userCanContribute($this->userList['u3']), 'collage-can-contribute');

        // add an entry
        // If an entry is added in the same second as a user subscribes, the condition is false.
        // A subscriber is considered to have a new entry in a collage when:
        //   collages_torrents.AddedOn > users_collage_subs.LastVisit
        // This is why we must
        sleep(1);

        $this->assertEquals(1, $collage->addEntry($this->tgroupList[0]->id(), $this->userList['u3']->id(), 'collage-add-entry'));
        $this->assertEquals(0, $collage->addEntry($this->tgroupList[0]->id(), $this->userList['u2']->id(), 'collage-add-dupe-entry'));
        $unread = $manager->subscribedTGroupCollageList($this->userList['u2']->id(), false);
        $this->assertCount(1, $unread, 'collage-one-unread');

        // catchup
        // TODO: this relies on a side effect in isSubscribed(), the clearing should be more explicit
        $this->assertTrue($collage->isSubscribed($this->userList['u2']), 'collage-catchup');
        $this->assertCount(0, $manager->subscribedTGroupCollageList($this->userList['u2']->id(), false), 'collage-none-unread');

        $this->assertEquals(1, $collage->toggleSubscription($this->userList['u2']), 'collage-unsubscribe');
        $this->assertFalse($collage->isSubscribed($this->userList['u2']), 'collage-is-no-longer-subcribed');
        $this->assertEquals(0, $collage->numSubscribers(), 'collage-no-subcribers');

        $this->assertStringStartsWith(date('Y-m-d '), $collage->entryCreated($this->tgroupList[0]->id()), 'collage-entry-created');
    }

    public function testCollageArtist(): void {
        $manager = new Gazelle\Manager\Collage;
        $this->collageList = [
            $manager->create(
                user:        $this->userList['u1'],
                categoryId:  CollageType::artist->value,
                name:        'phpunit collage artist ' . randomString(20),
                description: 'phpunit collage artist description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $this->userList['u2'],
                categoryId:  CollageType::artist->value,
                name:        'phpunit collage artist ' . randomString(20),
                description: 'phpunit collage artist description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
        ];

        $artistMan = new Gazelle\Manager\Artist;
        foreach ($this->artistName as $artistName) {
            $artist = $artistMan->findByName($artistName);
            $this->assertNotNull($artist, "find $artistName");
            $this->collageList[0]->addEntry($artistMan->findByName($artistName)?->id(), $this->userList['u1']->id());
        }
        $this->collageList[1]->addEntry($artistMan->findByName($this->artistName[1])?->id(), $this->userList['u1']->id());
        $this->collageList[1]->addEntry($artistMan->findByName($this->artistName[2])?->id(), $this->userList['u2']->id());
        $this->collageList[1]->addEntry($artistMan->findByName($this->artistName[3])?->id(), $this->userList['u3']->id());

        $this->assertEquals(3, $this->collageList[1]->numContributors(), 'collage-artist-contributor');
        $summary = $manager->artistSummary($artistMan->findByName($this->artistName[2])->id());
        $this->assertEquals(2, $summary['total'], 'collage-artist-summary-total');
        $this->assertCount(2, $summary['above'], 'collage-artist-summary-above');
        $this->assertCount(0, $summary['below'], 'collage-artist-summary-below');

        $this->assertStringStartsWith(
            date('Y-m-d '),
            $this->collageList[1]->entryCreated($artistMan->findByName($this->artistName[3])->id()),
            'collage-artist-entry-created'
        );
    }

    public function testCollageContribute(): void {
        $manager = new Gazelle\Manager\Collage;
        $this->collageList[] = $manager->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::label->value,
            name:        'phpunit collage contrib ' . randomString(20),
            description: 'phpunit collage contrib description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];
        $this->assertEquals(0, $collage->numContributors(), 'collage-no-contributors');

        // contribute! contribute!
        $u1 = $this->userList['u1'];
        $u2 = $this->userList['u2'];
        $u3 = $this->userList['u3'];
        $collage->addEntry($this->tgroupList[0]->id(), $u1->id());
        $collage->addEntry($this->tgroupList[1]->id(), $u3->id());
        $collage->addEntry($this->tgroupList[2]->id(), $u3->id());
        $collage->addEntry($this->tgroupList[3]->id(), $u3->id());
        $this->assertEquals(4, $collage->numEntries(), 'collage-has-4');
        $this->assertCount(4, $collage->topArtists(), 'collage-has-4-artists');
        $this->assertEquals(2, $collage->numContributors(), 'collage-two-contributors');
        $this->assertEquals(
            [
                $this->userList['u1']->id() => 1,
                $this->userList['u3']->id() => 3,
            ],
            $collage->contributors(),
            'collage-contributor-list'
        );
        $this->assertTrue($collage->userHasContributed($u1), 'collage-user-1-has-contrib');
        $this->assertFalse($collage->userHasContributed($u2), 'collage-user-2-no-contrib');
        $this->assertTrue($collage->userHasContributed($u3), 'collage-user-3-has-contrib');
        $this->assertEquals(3, $collage->countByUser($u3->id()), 'collage-contributor-three');
        $this->assertEquals($u1->id(), $collage->entryUserId($this->tgroupList[0]->id()), 'collage-contribution-by');

        $idList = array_map(fn($n) => $this->tgroupList[$n]->id(), range(0, 3));
        $this->assertEquals([$idList[0], $idList[1], $idList[2], $idList[3]], $collage->entryList(), 'collage-entry-list');

        $this->assertEquals(1, $collage->updateSequenceEntry($this->tgroupList[2]->id(), 1000), 'collage-entry-to-last');
        $this->assertEquals([$idList[0], $idList[1], $idList[3], $idList[2]], $collage->entryList(), 'collage-new-entry-last');

        $newOrder = [$idList[1], $idList[3], $idList[0], $idList[2]];
        $collage->updateSequence(implode('&', array_map(fn($id) => "li[]=$id", $newOrder)));
        $this->assertEquals($newOrder, $collage->entryList(), 'collage-new-entry-list');
        $entry = 2;
        $this->assertEquals($entry * 10, $collage->sequence($newOrder[$entry - 1]), 'collage-entry-sequence');

        $this->assertInstanceOf(
            Gazelle\Manager\Collage::class,
            $manager->setImageProxy(new Gazelle\Util\ImageProxy($u1)),
            'collage-manager-image-proxy'
        );
        $cover = $manager->tgroupCover($this->tgroupList[0]);
        $this->assertStringContainsString("image_group_{$this->tgroupList[0]->id()}", $cover, 'collage-tgroup-cover-id');
        $this->assertStringContainsString($this->tgroupList[0]->name(), $cover, 'collage-tgroup-cover-name');

        $this->assertEquals(1, $collage->removeEntry($this->tgroupList[1]->id()), 'collage-remove-entry');
        $this->assertEquals(3, $collage->numEntries(), 'collage-has-3');
        $this->assertEquals([$idList[3], $idList[0], $idList[2]], $collage->entryList(), 'collage-removed-entry-list');

        $this->assertTrue($collage->toggleAttr('sort-newest', true), 'collage-personal-sort-newest');
        $collage->addEntry($this->tgroupList[1]->id(), $u3->id());
        $this->assertEquals([$idList[1], $idList[3], $idList[0], $idList[2]], $collage->entryList(), 'collage-add-first');
    }

    public function testCollageFeature(): void {
        $manager = new Gazelle\Manager\Collage;
        $user    = $this->userList['u1'];
        $this->collageList = [
            $manager->create(
                user:        $user,
                categoryId:  CollageType::personal->value,
                name:        'phpunit collage feat 001 ' . randomString(20),
                description: 'phpunit collage feature description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $user,
                categoryId:  CollageType::personal->value,
                name:        'phpunit collage feat 002 ' . randomString(20),
                description: 'phpunit collage feature description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $user,
                categoryId:  CollageType::personal->value,
                name:        'phpunit collage feat 003 ' . randomString(20),
                description: 'phpunit collage feature description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $user,
                categoryId:  CollageType::personal->value,
                name:        'phpunit collage feat 004 ' . randomString(20),
                description: 'phpunit collage feature description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
        ];
        $personal = $manager->findPersonalByUserId($user->id());
        $this->assertEquals(
            array_map(fn($c) => $c->id(), [$this->collageList[0], $this->collageList[1], $this->collageList[2], $this->collageList[3]]),
            array_map(fn($c) => $c->id(), $personal),
            'collage-personal-list'
        );
        $this->assertTrue($personal[2]->setFeatured()->modify(), 'collage-set-featured');
        $personal = $manager->findPersonalByUserId($user->id());
        $this->assertEquals(
            array_map(fn($c) => $c->id(), [$this->collageList[2], $this->collageList[0], $this->collageList[1], $this->collageList[3]]),
            array_map(fn($c) => $c->id(), $personal),
            'collage-personal-list-featured'
        );

        $tgroupId = $this->tgroupList[0]->id();
        foreach ($this->collageList as $collage) {
            $collage->addEntry($tgroupId, $user->id());
        }
        $summary = $manager->tgroupPersonalSummary($tgroupId);
        $this->assertEquals(4, $summary['total'], 'collage-manager-personal-tgroup-total');
        $this->assertCount(COLLAGE_SAMPLE_THRESHOLD, $summary['above'], 'collage-manager-personal-tgroup-above');
        $this->assertEquals(COLLAGE_SAMPLE_THRESHOLD, count($this->collageList) - count($summary['below']), 'collage-manager-personal-tgroup-below');
    }

    public function testCollageJson(): void {
        $this->collageList[] = (new Gazelle\Manager\Collage)->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::staffPick->value,
            name:        'phpunit collage json ' . randomString(20),
            description: 'phpunit collage json description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];
        foreach (range(0, 3) as $n) {
            $collage->addEntry($this->tgroupList[$n]->id(), $this->userList['u3']->id());
        }
        $this->assertTrue((new Gazelle\User\Bookmark($this->userList['u1']))->create('collage', $collage->id()), 'collage-bookmark');

        $payload = (new Gazelle\Json\Collage(
                $collage,
                $this->userList['u1'],
                new Gazelle\Manager\TGroup,
                new Gazelle\Manager\Torrent,
            ))->payload();
        $this->assertEquals($collage->id(), $payload['id'], 'collage-json-id');
        $this->assertEquals('Staff picks', $payload['collageCategoryName'], 'collage-json-cat-name');
        $this->assertCount(4, $payload['torrentGroupIDList'], 'collage-json-entry-count');
        $this->assertTrue($payload['hasBookmarked'], 'collage-json-bookmarked');
    }

    public function testCollageLock(): void {
        $this->collageList[] = (new Gazelle\Manager\Collage)->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::chart->value,
            name:        'phpunit collage lock ' . randomString(20),
            description: 'phpunit collage lock description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];

        $this->assertFalse($collage->isLocked(), 'collage-is-not-locked');
        $this->assertTrue($collage->toggleLocked()->modify(), 'collage-lock');
        $this->assertTrue($collage->isLocked(), 'collage-is-now-locked');
        $this->assertFalse($collage->userCanContribute($this->userList['u1']), 'collage-locked-contribute');
        $this->assertTrue($collage->toggleLocked()->modify(), 'collage-unlock');
        $this->assertFalse($collage->isLocked(), 'collage-is-unlocked');
    }

    public function testCollageManager(): void {
        $manager = new Gazelle\Manager\Collage;
        $this->assertEquals(
            $this->userList['u1']->username() . "'s personal collage",
            $manager->personalCollageName($this->userList['u1']->username()),
            'collage-manager-personal-name'
        );

        $stem = 'phpunit collage man ';
        $this->collageList = [
            $manager->create(
                user:        $this->userList['u1'],
                categoryId:  CollageType::series->value,
                name:        $stem . randomString(20),
                description: 'phpunit collage man description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $this->userList['u1'],
                categoryId:  CollageType::series->value,
                name:        $stem . randomString(20),
                description: 'phpunit collage man description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $this->userList['u1'],
                categoryId:  CollageType::series->value,
                name:        $stem . randomString(20),
                description: 'phpunit collage man description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $this->userList['u2'],
                categoryId:  CollageType::series->value,
                name:        $stem . randomString(20),
                description: 'phpunit collage man description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
            $manager->create(
                user:        $this->userList['u2'],
                categoryId:  CollageType::genre->value,
                name:        $stem . randomString(20),
                description: 'phpunit collage man description',
                tagList:     implode(' ', $this->tagList(3)),
                logger:      new Gazelle\Log,
            ),
        ];
        $this->assertCount(
            count($this->collageList),
            $manager->autocomplete($stem),
            'collage-manager-autocomplete'
        );
        $nameList = array_map(fn($c) => $c->name(), $this->collageList);
        usort($nameList, 'strcasecmp');
        $autocomplete = $manager->autocomplete('phpunit collage auto');
        $this->assertEquals($nameList, array_column($autocomplete, 'value'), 'collage-manager-autocomplete');

        $tgroupId = $this->tgroupList[0]->id();
        $userId   = $this->userList['u3']->id();
        foreach ($this->collageList as $collage) {
            $collage->addEntry($tgroupId, $userId);
        }
        $summary = $manager->tgroupGeneralSummary($tgroupId);
        $this->assertEquals(5, $summary['total'], 'collage-manager-summary-tgroup-total');
        $this->assertCount(COLLAGE_SAMPLE_THRESHOLD, $summary['above'], 'collage-manager-summary-tgroup-above');
        $this->assertEquals(COLLAGE_SAMPLE_THRESHOLD, count($this->collageList) - count($summary['below']), 'collage-manager-summary-tgroup-below');
    }

    public function testCollagePersonal(): void {
        $this->collageList[] = (new Gazelle\Manager\Collage)->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::personal->value,
            name:        'phpunit collage personal ' . randomString(20),
            description: 'phpunit collage personal description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];
        // $this->userList['u1']->addCustomPrivilege('site_collages_manage');
        // $this->userList['u2']->addCustomPrivilege('site_collages_manage');

        $this->assertTrue($collage->isPersonal(), 'collage-is-not-personal');
        $this->assertTrue($collage->userCanContribute($this->userList['u1']), 'collage-can-contribute-personal');
        $this->assertFalse($collage->userCanContribute($this->userList['u2']), 'collage-cannot-contribute-personal');

        $collage->addEntry($this->tgroupList[0]->id(), $this->userList['u1']->id());
        $collage->addEntry($this->tgroupList[1]->id(), $this->userList['u3']->id());
        $collage->addEntry($this->tgroupList[3]->id(), $this->userList['u3']->id());
        $this->assertEquals(
            [
                $this->tgroupList[3]->id(),
                $this->tgroupList[1]->id(),
                $this->tgroupList[0]->id(),
            ],
            $collage->entryList(),
            'collage-personal-newest-first'
        );
        $this->assertTrue($collage->hasEntry($this->tgroupList[1]->id()), 'collage-entry-present');
        $this->assertFalse($collage->hasEntry($this->tgroupList[2]->id()), 'collage-entry-absent');

        $this->assertTrue($collage->toggleAttr('sort-newest', true), 'collage-personal-sort-newest');
        $collage->addEntry($this->tgroupList[2]->id(), $this->userList['u2']->id());
        $this->assertEquals(
            [
                $this->tgroupList[3]->id(),
                $this->tgroupList[1]->id(),
                $this->tgroupList[0]->id(),
                $this->tgroupList[2]->id(),
            ],
            $collage->entryList(),
            'collage-personal-newest-last'
        );

        $this->assertTrue($collage->toggleAttr('sort-newest', false), 'collage-personal-toggle-newest');
        $collage->addEntry($this->tgroupList[4]->id(), $this->userList['u2']->id());
        $this->assertEquals(
            [
                $this->tgroupList[4]->id(),
                $this->tgroupList[3]->id(),
                $this->tgroupList[1]->id(),
                $this->tgroupList[0]->id(),
                $this->tgroupList[2]->id(),
            ],
            $collage->entryList(),
            'collage-personal-newest-not-last'
        );
        // given that the default tag lists are successively longer slices
        // of the same list, the frequency is the same order as the source
        // list.
        $this->assertEquals(
            ["acoustic", "jazz", "metal", "rock"],
            $collage->rebuildTagList(),
            'collage-personal-rebuild-taglist'
        );
    }

    public function testCollageRemove(): void {
        $manager = new Gazelle\Manager\Collage;
        $name    = 'phpunit collage remove ' . randomString(20);
        $this->collageList[] = $manager->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::theme->value,
            name:        $name,
            description: 'phpunit collage remove description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $collage = $this->collageList[0];

        $this->assertEquals(1, $collage->remove(), 'collage-remove');
        $this->assertTrue($collage->isDeleted(), 'collage-is-deleted');

        $this->assertNull($manager->recoverByName($name . 'does not exist'), 'collage-recover-fail');
        $this->assertInstanceOf(Gazelle\Collage::class, $manager->recoverByName($name), 'collage-recover-by-name');

        $collage->remove();
        $this->assertInstanceOf(Gazelle\Collage::class, $manager->recoverById($collage->id()), 'collage-recover-by-id');
    }

    public function testCollageAjaxAdd(): void {
        $manager = new Gazelle\Manager\Collage;
        $name    = 'phpunit collage ajax ' . randomString(20);
        $collage = $this->collageList[] = $manager->create(
            user:        $this->userList['u1'],
            categoryId:  CollageType::personal->value,
            name:        $name,
            description: 'phpunit collage ajax description',
            tagList:     implode(' ', $this->tagList(3)),
            logger:      new Gazelle\Log,
        );
        $artMan    = new Gazelle\Manager\Artist;
        $tgMan     = new Gazelle\Manager\TGroup;

        $fail = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     0,
            entryId:       $this->tgroupList[2]->id(),
            name:          '',
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $response = json_decode($fail->response(), true);
        $this->assertEquals('collage not found', $response['error'], 'collage-ajax-collage-404');

        $fail = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     $collage->id(),
            entryId:       0,
            name:          '',
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $response = json_decode($fail->response(), true);
        $this->assertEquals('entry not found', $response['error'], 'collage-ajax-entry-404');

        $byEntry = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     $collage->id(),
            entryId:       $this->tgroupList[0]->id(),
            name:          '',
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $this->assertArrayHasKey('link', $byEntry->payload(), 'collage-ajax-add-entry-id');

        $byName = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     0,
            entryId:       $this->tgroupList[1]->id(),
            name:          $collage->name(),
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $this->assertArrayHasKey('link', $byName->payload(), 'collage-ajax-add-name');

        $byUrl = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     0,
            entryId:       $this->tgroupList[2]->id(),
            name:          $collage->publicLocation(),
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $this->assertArrayHasKey('link', $byUrl->payload(), 'collage-ajax-add-url');

        $fail = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     $collage->id(),
            entryId:       $this->tgroupList[0]->id(),
            name:          '',
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $this->assertEquals([], $fail->payload(), 'collage-ajax-add-already');
        $response = json_decode($fail->response(), true);
        $this->assertEquals('already present?', $response['error'], 'collage-ajax-error-already');

        $fail = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     $collage->id(),
            entryId:       $this->tgroupList[1]->id(),
            name:          '',
            user:          $this->userList['u2'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $response = json_decode($fail->response(), true);
        $this->assertEquals('personal', $response['error'], 'collage-ajax-error-personal');

        $collage->toggleLocked()->modify();
        $fail = new Gazelle\Json\Ajax\CollageAdd(
            collageId:     $collage->id(),
            entryId:       $this->tgroupList[1]->id(),
            name:          '',
            user:          $this->userList['u1'],
            manager:       $manager,
            artistManager: $artMan,
            tgroupManager: $tgMan,
        );
        $response = json_decode($fail->response(), true);
        $this->assertEquals('locked', $response['error'], 'collage-ajax-error-locked');
    }
}
