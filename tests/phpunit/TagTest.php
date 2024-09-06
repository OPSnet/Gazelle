<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class TagTest extends TestCase {
    protected const PREFIX = 'phpunit.';

    protected User    $user;
    protected TGroup  $tgroup;
    protected Request $request;

    public function tearDown(): void {
        if (isset($this->request)) {
            $this->request->remove();
        }
        if (isset($this->user)) {
            if (isset($this->tgroup)) {
                \GazelleUnitTest\Helper::removeTGroup($this->tgroup, $this->user);
            }
            $this->user->remove();
        }
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM tag_aliases WHERE BadTag LIKE 'phpunit.%'
        ");
        $db->prepared_query("
            DELETE t, tt, ttv
            FROM tags t
            LEFT JOIN torrents_tags tt ON (tt.TagID = t.ID)
            LEFT JOIN torrents_tags_votes ttv ON (ttv.TagID = t.ID)
            WHERE t.Name like 'phpunit.%'
        ");
    }

    public function testSanitize(): void {
        $manager = new Manager\Tag();
        $this->assertEquals('trim', $manager->sanitize(' trim '), 'tag-sanitize-trim');
        $this->assertEquals('lower', $manager->sanitize('Lower'), 'tag-sanitize-lower');
        $this->assertEquals('heavy.metal', $manager->sanitize('heavy metal'), 'tag-sanitize-internal-space');
        $this->assertEquals('post.rock', $manager->sanitize('post rock'), 'tag-sanitize-internal-space');
        $this->assertEquals('alpha1', $manager->sanitize('a@l,p#_h"a!1'), 'tag-sanitize-alphanum');
        $this->assertEquals('dot.dot', $manager->sanitize('dot...dot'), 'tag-sanitize-double-dot');
    }

    public function testNormalize(): void {
        $manager = new Manager\Tag();
        $this->assertEquals('dub', $manager->normalize('Dub dub  DUB! '), 'tag-normalize-dup');
        $this->assertEquals('neo.folk', $manager->normalize('neo...folk neo-folk'), 'tag-normalize-more');
        $this->assertEquals('pop rock', $manager->normalize(' pop rock rock pop Rock'), 'tag-normalize-two');
    }

    public function testCreate(): void {
        $manager = new Manager\Tag();
        $name    = self::PREFIX . randomString(5);
        $this->assertNull($manager->findByName($name), 'tag-lookup-fail');

        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(6), 'tag');
        $tag = $manager->create($name, $this->user);
        $this->assertInstanceOf(Tag::class, $tag, 'tag-find-by-id');
        $this->assertEquals($tag->id(), $manager->findByName($tag->name())->id(), 'tag-method-lookup');
        $this->assertEquals($name, $tag->name(), 'tag-method-name');
        $this->assertEquals($name, $manager->findByName($tag->name())->name(), 'tag-find-by-name');

        $find = $manager->findById($tag->id());
        $this->assertEquals($tag->id(), $find->id(), 'tag-find-by-id');

        $this->user->addBounty(500 * 1024 ** 3);
        $this->request = \GazelleUnitTest\Helper::makeRequestMusic($this->user, 'phpunit tag create request');
        $tag->addRequest($this->request);

        // rename to a new tag
        $new = "$name." . randomString(4);
        $this->assertNull($manager->findByName($new), 'tag-lookup-new-fail');
        $newTag = $manager->softCreate($new, $this->user);
        $this->assertEquals(
            1,
            $manager->rename( $tag, [$newTag], $this->user),
            'tag-rename'
        );
        $find = $manager->findByName($new);
        $this->assertInstanceOf(\Gazelle\Tag::class, $find, 'tag-find');
        $this->assertEquals($find->id(), $newTag->id(), 'tag-lookup-new-success');

        // rename to an existing tag
        $new2    = "$name." . randomString(5);
        $new2Tag = $manager->create($new2, $this->user);
        $this->assertEquals(1, $manager->rename($newTag, [$new2Tag], $this->user), 'tag-existing-rename');
        $this->assertEquals($new2Tag->id(), $manager->findByName($new2Tag->name())->id(), 'tag-lookup-existing-success');

        // Is empty because vote counts below 10 are ignored,
        // but at least we know the SQL is syntactically valid.
        $this->assertCount(0, $manager->userTopTagList($this->user), 'tag-user-count');
    }

    public function testSoftCreate(): void {
        $manager    = new Manager\Tag();
        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(6), 'tag');
        $valid      = 'phpunit.soft.' . randomString(6);

        $this->assertTrue($manager->validName($valid), 'tag-valid-name');
        $t1 = $manager->softCreate($valid, $this->user);
        $t2 = $manager->softCreate($valid, $this->user);
        $this->assertEquals($t1->id(), $t2->id(), 'tag-soft-create-valid');
    }

    public function testAlias(): void {
        $manager    = new Manager\Tag();
        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(6), 'tag');

        $bad  = $manager->create(self::PREFIX . randomString(10), $this->user);
        $good = $manager->create(self::PREFIX . randomString(10), $this->user);
        $this->assertNotEquals($bad->id(), $good->id(), 'tag-just-try-again');
        $aliasId =  $manager->createAlias($bad->name(), $good->name());
        $this->assertEquals($aliasId, $manager->lookupBad($bad->name()), 'tag-lookup-bad');

        $exclude    = $manager->create(self::PREFIX . randomString(10), $this->user);
        $excludeBad = $manager->create(self::PREFIX . randomString(10), $this->user);
        $manager->createAlias($excludeBad->name(), $exclude->name());
        $this->assertEquals(
            [
                'include' => [str_replace('.', '_', $good->name())],
                'exclude' => ['!' . str_replace('.', '_', $exclude->name())],
            ],
            $manager->replaceAliasList(
            [
                'include' => [str_replace('.', '_', $bad->name())],
                'exclude' => ['!' . str_replace('.', '_', $excludeBad->name())],
            ],
            ),
            'tag-replace-alias'
        );
        $tagList = [
            'include' => [$good->name()],
            'exclude' => ['!' . $exclude->name()],
        ];
        $input = "{$good->name()}, !{$exclude->name()}";
        $this->assertEquals(
            [
                "input"     => $input,
                "predicate" => "{$good->name()} !{$exclude->name()}",
            ],
            $manager->sphinxFilter($tagList, true, true),
            'tag-sphinx-neg-all'
        );
        $this->assertEquals(
            [
                "input"     => $input,
                "predicate" => "( {$good->name()} ) !{$exclude->name()}",
            ],
            $manager->sphinxFilter($tagList, true, false),
            'tag-sphinx-neg-noall'
        );
        $this->assertEquals(
            [
                "input"     => $input,
                "predicate" => "{$good->name()} \\\\!{$exclude->name()}",
            ],
            $manager->sphinxFilter($tagList, false, true),
            'tag-sphinx-noneg-all'
        );
        $this->assertEquals(
            [
                "input"     => $input,
                "predicate" => "( {$good->name()} | \\\\!{$exclude->name()} )",
            ],
            $manager->sphinxFilter($tagList, false, false),
            'tag-sphinx-noneg-noall'
        );

        $notgood = $good->name() . '.nope';
        $this->assertEquals(1, $manager->modifyAlias($aliasId, $notgood, $good->name()), 'tag-rename-alias');
        $list = array_filter(
            $manager->aliasList(),
            fn($t) => $t['BadTag'] == strtr($notgood, '.', '_'),
        );
        $this->assertCount(1, $list, 'tag-alias-list');
        $this->assertEquals($good->name(), $manager->resolve($notgood), 'tag-resolve-alias');
        $this->assertEquals(1, $manager->removeAlias($aliasId), 'tag-remove-alias');
    }

    public function testOfficial(): void {
        $manager    = new Manager\Tag();
        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(6), 'tag');
        $tag        = $manager->create(self::PREFIX . randomString(10), $this->user);
        $this->assertEquals($tag->id(), $manager->officialize($tag->name(), $this->user)->id(), 'tag-officalize-existing');
        $list = array_filter($manager->genreList(), fn($t) => $t == $tag->name());
        $this->assertCount(1, $list, 'tag-genre-list');

        $official = $manager->officialize(self::PREFIX . 'off.' . randomString(10), $this->user);
        $this->assertNotEquals($tag->id(), $official->id(), 'tag-officialize-new');
        $officialName = $official->name();
        $list = array_filter(
            $manager->officialList(),
            fn($t) => $t->name() == $officialName
        );
        $this->assertCount(1, $list, 'tag-official-list');
        $this->assertEquals(1, $manager->unofficialize([$official->id()]), 'tag-unofficialize');
        $this->assertCount(
            0,
            array_filter($manager->officialList(), fn($t) => $t->name() == $officialName),
            'tag-empty-official-list'
        );
    }

    public function testTGroup(): void {
        $this->user   = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(8), 'tag.tgroup');
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['Tag Girl ' . randomString(12)]],
            tagName:    ['phpunit.electronic', 'phpunit.folk', 'phpunit.disco'],
            user:       $this->user,
        );
        \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->user,
        );

        $manager = new Manager\Tag();
        $folk = $manager->findByName('phpunit.folk');
        $this->assertFalse(
            $folk->hasVoteTGroup($this->tgroup, $this->user),
            'tag-has-no-vote'
        );
        $tag    = $manager->findByName('phpunit.electronic');
        $result = $tag->tgroupList();
        $item   = current($result);
        $this->assertCount(1, $result, 'tag-torrent-lookup');
        $this->assertEquals($this->tgroup->id(), $item['torrentGroupId'], 'tag-found-tgroup');
        $this->assertEquals(
            1,
            $folk->voteTGroup($this->tgroup, $this->user, 'up'),
            'tag-tgroup-vote'
        );
        $db = DB::DB();
        $this->assertTrue(
            $folk->hasVoteTGroup($this->tgroup, $this->user),
            'tag-has-no-vote'
        );
        // not enough uses to have a meaningful result, but at least the query is run
        $this->assertCount(0, $manager->autocompleteAsJson('phpun'), 'tag-autocomplete');
        $this->assertCount(0, $folk->requestList(), 'tag-request-lookup');
    }

    public function testReAdd(): void {
        $this->user   = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(8), 'tag.readd');
        $manager      = new Manager\Tag();
        $name         = self::PREFIX . randomString(10);
        $tag          = $manager->create($name, $this->user);
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['Tag Girl ' . randomString(12)]],
            tagName:    [$name],
            user:       $this->user,
        );
        $this->assertEquals(2, $tag->addTGroup($this->tgroup, $this->user, 0), 'tag-re-add');
    }

    public function testSplitNew(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(8), 'tag.split');
        $manager = new Manager\Tag();
        $name    = self::PREFIX . randomString(10);
        $tag     = $manager->create($name, $this->user);

        $this->user->addBounty(500 * 1024 ** 3);
        $this->request = \GazelleUnitTest\Helper::makeRequestMusic($this->user, 'phpunit tag split new request');
        $tag->addRequest($this->request);
        $this->assertEquals(1, $tag->flush()->uses(), 'tag-instance-use-1');
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['Tag Girl ' . randomString(12)]],
            tagName:    [$name],
            user:       $this->user,
        );
        $this->assertEquals(2, $tag->flush()->uses(), 'tag-instance-use-2');

        // split tag into two new (indie.rock.alt.rock => indie.rock, alt.rock)
        $this->assertEquals(
            4, // 2 for each tgroup and request
            $manager->rename(
                $tag,
                [
                    $manager->softCreate("$name.1", $this->user),
                    $manager->softCreate("$name.2", $this->user),
                ],
                $this->user
            ),
            'tag-new-split',
        );
        $this->assertEquals(
            ["$name.1", "$name.2"],
            $this->request->flush()->tagNameList(),
            'tag-split-new-request',
        );
        $this->assertEquals(
            ["$name.1", "$name.2"],
            array_values(array_map(fn($t) => $t['name'], $this->tgroup->flush()->tagList())),
            'tag-split-new-tgroup',
        );

        $this->assertNull($manager->findByName($name), 'tag-new-gone');
        $this->assertEquals(
            2,
            $manager->findByName("$name.2")->uses(),
            'tag-new-uses',
        );
    }

    public function testSplitExisting(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(8), 'tag.split');
        $manager = new Manager\Tag();
        $name    = self::PREFIX . randomString(10);
        $tag     = $manager->create($name, $this->user);

        $this->user->addBounty(500 * 1024 ** 3);
        $this->request = \GazelleUnitTest\Helper::makeRequestMusic($this->user, 'phpunit user promote request');
        $tag->addRequest($this->request);
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['Tag Girl ' . randomString(12)]],
            tagName:    [$name],
            user:       $this->user,
        );

        // split tag into two existing tags
        $nameList = ["$name.3", "$name.4"];
        $tagList = array_map(fn($n) => $manager->create($n, $this->user), $nameList);
        $this->assertEquals(
            4,
            $manager->rename(
                $tag,
                [
                    $manager->softCreate("$name.3", $this->user),
                    $manager->softCreate("$name.4", $this->user),
                ],
                $this->user,
            ),
            'tag-existing-split',
        );
        $this->assertEquals(
            ["$name.3", "$name.4"],
            $this->request->flush()->tagNameList(),
            'tag-split-existing-request',
        );
        $this->assertEquals(
            ["$name.3", "$name.4"],
            array_values(array_map(fn($t) => $t['name'], $this->tgroup->flush()->tagList())),
            'tag-split-existing-tgroup',
        );

        $this->assertNull($manager->findByName($name), 'tag-existing-gone');
        $this->assertEquals(
            2,
            $manager->findByName("$name.3")->uses(),
            'tag-existing-uses',
        );
    }

    public function testTag(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('tag.' . randomString(8), 'tag.tgroup');
        $manager    = new Manager\Tag();
        $name       = self::PREFIX . randomString(10);
        $tag        = $manager->create($name, $this->user);
        $this->assertInstanceOf(Tag::class, $tag, 'tag-instance-find');
        $this->assertEquals($name, $tag->name(), 'tag-instance-name');
        $this->assertEquals(
            "<a href=\"torrents.php?taglist=$name\">$name</a>",
            $tag->link(),
            'tag-instance-link'
        );
        $this->assertEquals("torrents.php?taglist=$name", $tag->location(), 'tag-instance-location');
        $this->assertEquals('other', $tag->type(), 'tag-instance-table-name');
        $manager->officialize($name, $this->user);
        $this->assertEquals('genre', $tag->flush()->type(), 'tag-instance-genre');
        $this->assertEquals($this->user->id(), $tag->userId(), 'tag-instance-creator');
    }

    public function testTop10(): void {
        $manager = new Manager\Tag();
        $this->assertIsArray($manager->topTGroupList(1), 'tag-top10-tgroup');
        $this->assertIsArray($manager->topRequestList(1), 'tag-top10-request');
        $this->assertIsArray($manager->topVotedList(1), 'tag-top10-voted');

        $ajax = new Json\Top10\Tag(
            details: 'all',
            limit: 10,
            manager: $manager,
        );
        $payload = $ajax->payload();
        $this->assertCount(3, $payload, 'tag-top10-all-payload');
        $this->assertEquals('ut', $payload[0]['tag'], 'tag-top10-payload-ut');
        $this->assertEquals('ur', $payload[1]['tag'], 'tag-top10-payload-ur');
        $this->assertEquals('v', $payload[2]['tag'], 'tag-top10-payload-v');

        $this->assertCount(0, (new Json\Top10\Tag('bogus', 1, $manager))->payload(), 'tag-top10-bogus-payload');
    }
}
