<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class TagTest extends TestCase {
    protected const PREFIX = 'phpunit.';

    protected User    $user;
    protected Request $request;
    protected TGroup  $tgroup;

    public function tearDown(): void {
        if (isset($this->request)) {
            $this->request->remove();
        }
        if (isset($this->tgroup)) {
            Helper::removeTGroup($this->tgroup, $this->user);
        }
        if (isset($this->user)) {
            Helper::removeUser($this->user);
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
            WHERE t.Name REGEXP '^(phpunit\.|ut\.|[1-9][0-9]+)' OR t.Name = ''
        ");
        $pg = new DB\Pg(PG_RW_DSN);
        $pg->query("
            delete from tag_alias where alias_allow ~ '^phpunit'
        ");
        $pg->query("
            delete from tag where name ~ '^(phpunit\.|ut\.|[1-9][0-9]+)' or name = ''
        ");
    }

    public function testSanitize(): void {
        $manager = new Manager\Tag();
        $this->assertSame('trim', $manager->sanitize(' trim '), 'tag-sanitize-trim');
        $this->assertSame('lower', $manager->sanitize('Lower'), 'tag-sanitize-lower');
        $this->assertSame('heavy.metal', $manager->sanitize('heavy metal'), 'tag-sanitize-internal-space');
        $this->assertSame('post.rock', $manager->sanitize('post rock'), 'tag-sanitize-internal-space');
        $this->assertSame('alpha1', $manager->sanitize('a@l,p#_h"a!1'), 'tag-sanitize-alphanum');
        $this->assertSame('dot.dot', $manager->sanitize('dot...dot'), 'tag-sanitize-double-dot');
    }

    public function testNormalize(): void {
        $manager = new Manager\Tag();
        $this->assertSame('', $manager->normalize('              '), 'tag-normalize-space');
        $this->assertSame('dub', $manager->normalize('Dub dub  DUB! '), 'tag-normalize-dup');
        $this->assertSame('neo.folk', $manager->normalize('neo...folk neo-folk'), 'tag-normalize-more');
        $this->assertSame('pop rock', $manager->normalize(' pop rock rock pop Rock'), 'tag-normalize-two');
    }

    public function testCreate(): void {
        $manager = new Manager\Tag();
        $name    = self::PREFIX . strtolower(randomString(5));
        $this->assertNull($manager->findByName($name), 'tag-lookup-fail');

        $this->user = Helper::makeUser('tag.' . randomString(6), 'tag');
        $this->user->requestContext()->setViewer($this->user);
        $tag = $manager->create($name, $this->user);
        $this->assertSame($tag->id, $manager->findByName($tag->name())?->id, 'tag-method-lookup');
        $this->assertSame($name, $tag->name(), 'tag-method-name');
        $this->assertSame($name, $manager->findByName($tag->name())->name(), 'tag-find-by-name');

        $find = $manager->findById($tag->id);
        $this->assertSame($tag->id, $find?->id, 'tag-find-by-id');

        $this->user->addBounty(500 * 1024 ** 3);
        $this->request = Helper::makeRequestMusic($this->user, 'phpunit tag create request');
        $tag->addRequest($this->request);

        // rename to a new tag
        $new = "$name." . randomString(4);
        $this->assertNull($manager->findByName($new), 'tag-lookup-new-fail');
        $newTag = $manager->softCreate($new, $this->user);
        $this->assertInstanceOf(\Gazelle\Tag::class, $newTag, 'tag-new-found');
        $this->assertSame(
            1,
            $manager->rename( $tag, [$newTag], $this->user),
            'tag-rename'
        );
        $find = $manager->findByName($new);
        $this->assertInstanceOf(Tag::class, $find, 'tag-find-by-id'); /** @phpstan-ignore-line method.impossibleType */
        $this->assertSame($find->id, $newTag->id, 'tag-lookup-new-success');

        // rename to an existing tag
        $new2    = "$name." . randomString(5);
        $new2Tag = $manager->create($new2, $this->user);
        $this->assertSame(
            1,
            $manager->rename($newTag, [$new2Tag], $this->user),
            'tag-existing-rename'
        );
        $this->assertSame(
            $new2Tag->id,
            (int)$manager->findByName($new2Tag->name())?->id,
            'tag-lookup-existing-success'
        );

        // Is empty because vote counts below 10 are ignored,
        // but at least we know the SQL is syntactically valid.
        $this->assertCount(0, $manager->userTopTagList($this->user), 'tag-user-count');
    }

    public function testSoftCreate(): void {
        $manager    = new Manager\Tag();
        $this->user = Helper::makeUser('tag.' . randomString(6), 'tag');
        $valid      = 'phpunit.soft.' . randomString(6);

        $this->assertTrue($manager->validName($valid), 'tag-valid-name');
        $t1 = $manager->softCreate($valid, $this->user);
        $t2 = $manager->softCreate($valid, $this->user);
        $this->assertInstanceOf(Tag::class, $t1, 'tag-t1-found');
        $this->assertInstanceOf(Tag::class, $t2, 'tag-t2-found');
        $this->assertSame($t1->id, $t2->id, 'tag-soft-create-valid');
    }

    public function testAlias(): void {
        $manager    = new Manager\Tag();
        $this->user = Helper::makeUser('tag.' . randomString(6), 'tag');

        $bad  = $manager->create(self::PREFIX . randomString(10), $this->user);
        $good = $manager->create(self::PREFIX . randomString(10), $this->user);
        $this->assertNotSame($bad->id, $good->id, 'tag-just-try-again');
        $aliasId =  $manager->createAlias($bad->name(), $good->name());
        $this->assertSame($aliasId, $manager->lookupBad($bad->name()), 'tag-lookup-bad');

        $exclude    = $manager->create(self::PREFIX . randomString(10), $this->user);
        $excludeBad = $manager->create(self::PREFIX . randomString(10), $this->user);
        $manager->createAlias($excludeBad->name(), $exclude->name());
        $this->assertSame(
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
        $found = $manager->listAlias(false);
        $this->assertGreaterThan(0, count($found), 'tag-alias-total');
        $this->assertSame(
            ['id' => $aliasId, 'bad' => $bad->name(), 'alias' => $good->name()],
            $found[$aliasId],
            'tag-alias-list',
        );
        $tagList = [
            'include' => [$good->name()],
            'exclude' => ['!' . $exclude->name()],
        ];
        $input = "{$good->name()}, !{$exclude->name()}";
        $this->assertSame(
            [
                "input"     => $input,
                "predicate" => "{$good->name()} !{$exclude->name()}",
            ],
            $manager->sphinxFilter($tagList, true, true),
            'tag-sphinx-neg-all'
        );
        $this->assertSame(
            [
                "input"     => $input,
                "predicate" => "( {$good->name()} ) !{$exclude->name()}",
            ],
            $manager->sphinxFilter($tagList, true, false),
            'tag-sphinx-neg-noall'
        );
        $this->assertSame(
            [
                "input"     => $input,
                "predicate" => "{$good->name()} \\\\!{$exclude->name()}",
            ],
            $manager->sphinxFilter($tagList, false, true),
            'tag-sphinx-noneg-all'
        );
        $this->assertSame(
            [
                "input"     => $input,
                "predicate" => "( {$good->name()} | \\\\!{$exclude->name()} )",
            ],
            $manager->sphinxFilter($tagList, false, false),
            'tag-sphinx-noneg-noall'
        );

        $notgood = $good->name() . '.nope';
        $this->assertSame(1, $manager->modifyAlias($aliasId, $notgood, $good->name()), 'tag-rename-alias');
        $list = array_filter(
            $manager->aliasList(),
            fn($t) => $t['BadTag'] == strtr($notgood, '.', '_'),
        );
        $this->assertCount(1, $list, 'tag-alias-list');
        $this->assertSame($good->name(), $manager->resolve($notgood), 'tag-resolve-alias');
        $this->assertSame(1, $manager->removeAlias($aliasId), 'tag-remove-alias');
    }

    public function testOfficial(): void {
        $manager    = new Manager\Tag();
        $this->user = Helper::makeUser('tag.' . randomString(6), 'tag');
        $tag        = $manager->create(self::PREFIX . randomString(10), $this->user);
        $this->assertSame($tag->id, $manager->officialize($tag->name(), $this->user)->id, 'tag-officalize-existing');
        $list = array_filter($manager->genreList(), fn($t) => $t == $tag->name());
        $this->assertCount(1, $list, 'tag-genre-list');

        $official = $manager->officialize(self::PREFIX . 'off.' . randomString(10), $this->user);
        $this->assertNotSame($tag->id, $official->id, 'tag-officialize-new');
        $officialName = $official->name();
        $list = array_filter(
            $manager->officialList(),
            fn($t) => $t->name() == $officialName
        );
        $this->assertCount(1, $list, 'tag-official-list');
        $this->assertSame(1, $manager->unofficialize([$official->id]), 'tag-unofficialize');
        $this->assertCount(
            0,
            array_filter($manager->officialList(), fn($t) => $t->name() == $officialName),
            'tag-empty-official-list'
        );
    }

    public function testNameList(): void {
        $manager    = new Manager\Tag();
        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.tgroup');
        $t1 = $manager->softCreate('phpunit.metal', $this->user);
        $t2 = $manager->softCreate('phpunit.plastic', $this->user);
        $this->assertEqualsCanonicalizing(
            [$t1, $t2],
            $manager->nameToTagList(
                'phpunit.metal  phpunit.plastic  ### '
                . randomString(40)
            ),
            'tag-name-tag-list',
        );
    }

    public function testNameListEmpty(): void {
        $manager = new Manager\Tag();

        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.tgroup');
        $manager->softCreate('', $this->user);

        $this->assertSame([], $manager->nameToTagList(''), 'tag-name-tag-list-empty');
        $this->assertSame([], $manager->nameToTagList('   '), 'tag-name-tag-list-whitespace');
        $this->assertSame([], $manager->nameToTagList(' , , '), 'tag-name-tag-list-commas-only');

        $tag        = $manager->softCreate('phpunit.trailing', $this->user);
        $this->assertEqualsCanonicalizing(
            [$tag],
            $manager->nameToTagList('phpunit.trailing,'),
            'tag-name-tag-list-trailing-comma',
        );
        $this->assertEqualsCanonicalizing(
            [$tag],
            $manager->nameToTagList(' , phpunit.trailing ,, '),
            'tag-name-tag-list-surrounding-commas',
        );
    }

    public function testTGroup(): void {
        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.tgroup');
        $this->user->requestContext()->setViewer($this->user);
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit tag ' . randomString(6)]],
            tagName:    ['phpunit.electronic', 'phpunit.folk', 'phpunit.disco'],
            user:       $this->user,
        );
        Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->user,
        );

        $manager = new Manager\Tag();
        $folk = $manager->findByName('phpunit.folk');
        $this->assertInstanceOf(Tag::class, $folk, 'tag-folk-found');
        $this->assertFalse(
            $folk->hasVoteTGroup($this->tgroup, $this->user),
            'tag-has-no-vote'
        );
        $tag    = $manager->findByName('phpunit.electronic');
        $this->assertInstanceOf(Tag::class, $tag, 'tag-tag-found');
        $result = $tag->tgroupList();
        $item   = array_first($result);
        $this->assertCount(1, $result, 'tag-torrent-lookup');
        $this->assertSame($this->tgroup->id, $item['torrentGroupId'], 'tag-found-tgroup');
        $this->assertSame(
            1,
            $folk->voteTGroup($this->tgroup, $this->user, 'up'),
            'tag-tgroup-vote'
        );
        $this->assertTrue(
            $folk->hasVoteTGroup($this->tgroup, $this->user),
            'tag-has-no-vote'
        );
        // not enough uses to have a meaningful result, but at least the query is run
        $this->assertCount(0, $manager->autocompleteAsJson('phpun'), 'tag-autocomplete');
        $this->assertCount(0, $folk->requestList(), 'tag-request-lookup');
    }

    public function testReAdd(): void {
        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.readd');
        $this->user->requestContext()->setViewer($this->user);
        $manager      = new Manager\Tag();
        $name         = self::PREFIX . randomString(10);
        $tag          = $manager->create($name, $this->user);
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit tag ' . randomString(6)]],
            tagName:    [$name],
            user:       $this->user,
        );
        $this->assertSame(2, $tag->addTGroup($this->tgroup, $this->user, 0), 'tag-re-add');
    }

    public function testSplitNew(): void {
        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.split');
        $this->user->requestContext()->setViewer($this->user);
        $manager = new Manager\Tag();
        $name    = self::PREFIX . strtolower(randomString(10));
        $tag     = $manager->create($name, $this->user);

        $this->user->addBounty(500 * 1024 ** 3);
        $this->request = Helper::makeRequestMusic($this->user, 'phpunit tag split new request');
        $tag->addRequest($this->request);
        $this->assertSame(1, $tag->flush()->uses(), 'tag-instance-use-1');
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit tag ' . randomString(6)]],
            tagName:    [$name],
            user:       $this->user,
        );
        $this->assertSame(2, $tag->flush()->uses(), 'tag-instance-use-2');

        // split tag into two new (indie.rock.alt.rock => indie.rock, alt.rock)
        $this->assertSame(
            3, // 2 for tgroup and 1 for request
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
        $this->assertSame(
            ["$name.1", "$name.2"],
            $this->request->flush()->tagNameList(),
            'tag-split-new-request',
        );
        $this->assertSame(
            ["$name.1", "$name.2"],
            array_values(array_map(fn($t) => $t['name'], $this->tgroup->flush()->tagList())),
            'tag-split-new-tgroup',
        );

        $this->assertNull($manager->findByName($name), 'tag-new-gone');
        $this->assertSame(
            2,
            $manager->findByName("$name.2")?->uses(),
            'tag-new-uses',
        );
    }

    public function testSplitExisting(): void {
        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.split');
        $this->user->requestContext()->setViewer($this->user);
        $manager = new Manager\Tag();
        $name    = self::PREFIX . strtolower(randomString(10));
        $tag     = $manager->create($name, $this->user);

        $this->user->addBounty(500 * 1024 ** 3);
        $this->request = Helper::makeRequestMusic($this->user, 'phpunit user promote request');
        $tag->addRequest($this->request);
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit tag ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit tag ' . randomString(6)]],
            tagName:    [$name],
            user:       $this->user,
        );

        // split tag into two existing tags
        $nameList = ["$name.3", "$name.4"];
        array_map(fn($n) => $manager->create($n, $this->user), $nameList);
        $this->assertSame(
            3,
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
        $this->assertSame(
            ["$name.3", "$name.4"],
            $this->request->flush()->tagNameList(),
            'tag-split-existing-request',
        );
        $this->assertSame(
            ["$name.3", "$name.4"],
            array_values(array_map(fn($t) => $t['name'], $this->tgroup->flush()->tagList())),
            'tag-split-existing-tgroup',
        );

        $this->assertNull($manager->findByName($name), 'tag-existing-gone');
        $this->assertSame(
            2,
            $manager->findByName("$name.3")?->uses(),
            'tag-existing-uses',
        );
    }

    public function testTag(): void {
        $this->user = Helper::makeUser('tag.' . randomString(8), 'tag.tgroup');
        $manager    = new Manager\Tag();
        $name       = self::PREFIX . strtolower(randomString(10));
        $tag        = $manager->create($name, $this->user);
        $this->assertSame($name, $tag->name(), 'tag-instance-name');
        $this->assertSame(
            "<a href=\"torrents.php?taglist=$name\">$name</a>",
            $tag->link(),
            'tag-instance-link'
        );
        $this->assertSame("torrents.php?taglist=$name", $tag->location(), 'tag-instance-location');
        $this->assertSame('other', $tag->type(), 'tag-instance-table-name');
        $manager->officialize($name, $this->user);
        $this->assertSame('genre', $tag->flush()->type(), 'tag-instance-genre');
        $this->assertSame($this->user->id, $tag->userId(), 'tag-instance-creator');
    }

    public function testTop10(): void {
        global $Cache;
        $Cache->delete_multi([
            "toptaguse_1", "toptagreq_1", "toptagvote_1",
        ]);
        $manager = new Manager\Tag();
        $this->assertGreaterThanOrEqual(0, count($manager->topTGroupList(1)), 'tag-top10-tgroup');
        $this->assertGreaterThanOrEqual(0, count($manager->topRequestList(1)), 'tag-top10-request');
        $this->assertGreaterThanOrEqual(0, count($manager->topVotedList(1)), 'tag-top10-voted');

        $payload = new Json\Top10\Tag('all', 10)->payload();
        $this->assertCount(3, $payload, 'tag-top10-all-payload');
        $this->assertSame('ut', $payload[0]['tag'], 'tag-top10-payload-ut');
        $this->assertSame('ur', $payload[1]['tag'], 'tag-top10-payload-ur');
        $this->assertSame('v', $payload[2]['tag'], 'tag-top10-payload-v');

        $this->assertCount(0, new Json\Top10\Tag('bogus', 1)->payload(), 'tag-top10-bogus-payload');
    }

    public function testTagRejection(): void {
        $this->user = Helper::makeUser('tag.' . randomString(6), 'tag');
        $manager    = new Manager\Tag();
        $name       = 'reject.' . strtolower(randomString(16));

        $id = $manager->createRejected($name, $this->user);
        $this->assertGreaterThan(0, $id, 'tag-reject-create');
        $this->assertSame(
            0,
            $manager->createRejected($name, $this->user),
            'tag-reject-duplicate',
        );
        $list = array_filter($manager->rejectedList(), fn ($t) => $t['name'] === $name);
        $this->assertCount(1, $list, 'tag-reject-list-count');
        $this->assertSame($name, array_first($list)['name'], 'tag-reject-list-name');
        $this->assertSame(
            1,
            $manager->removeRejectedList([$id]),
            'tag-reject-remove',
        );
    }
}
