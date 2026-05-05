<?php

namespace Gazelle;

use Gazelle\Enum\UserMatchQuality;
use Gazelle\Enum\UserMatchSort;
use Gazelle\UserMatch\MatchResult;
use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class ListMatcherTest extends TestCase {
    protected UserMatch\ListMatcher $matcher;
    protected array $users = [];

    public function setUp(): void {
        $sortKey       = Enum\UserMatchSort::score;
        $direction     = Enum\Direction::descending;
        $this->matcher = new UserMatch\ListMatcher($sortKey, $direction);
    }

    public function tearDown(): void {
        $db = DB::DB();
        $pg = new DB\Pg(PG_RW_DSN);
        foreach ($this->users as $user) {
            $user->remove();
            $db->prepared_query("
                DELETE FROM xbt_snatched WHERE uid = ?
                ", $user->id
            );
            $pg->prepared_query("
                delete from ip_history where id_user = ?
                ", $user->id
            );
        }
        $this->users = [];
    }

    public function testExtract(): void {
        $this->assertEmpty($this->matcher->extract(''), 'listmatcher-extract-empty');
        $this->assertEmpty($this->matcher->extract('garbage'), 'listmatcher-extract-garbage');
        $this->assertEmpty($this->matcher->extract('1.2.333.4'), 'listmatcher-extract-invalid');
        $this->assertEquals([['1.2.3.4', null, null]], $this->matcher->extract('1.2.3.4'), 'listmatcher-extract-simple');
        $this->assertEquals([['1.2.3.4', null, null]],
            $this->matcher->extract("aaaaaaa\naaaaa 1.2.3.4 aaaaaaa\naaaaaaa"),
            'listmatcher-extract-simple-extra'
        );
        $this->assertEquals([
                ['1.2.3.4', null, null],
                ['2.3.4.5', null, null],
            ],
            $this->matcher->extract("aaaaaaa\naaaaa 1.2.3.4 aaaaaaa\n2.3.4.5 aaaaaaa"),
            'listmatcher-extract-multi-extra'
        );
        $this->assertEquals([
                ['1.2.3.4', new \DateTimeImmutable('2020-02-03 12:34'), null],
            ],
            $this->matcher->extract("1.2.3.4 2020-02-03 12:34"),
            'listmatcher-extract-single-date'
        );
        $this->assertEquals([
                ['1.2.3.4', new \DateTimeImmutable('2020-02-03 12:34'), new \DateTimeImmutable('2021-03-04 23:12:03.123')],
                ['2.3.4.5', null, null],
                ['4.4.4.4', new \DateTimeImmutable('2022-05-20T22:11:01'), null]
            ],
            $this->matcher->extract(
                "garbage\ngarbage 1.2.3.4 2020-02-03 12:34 stuff 2021-03-04T23:12:03.123 morestuff
                stuff
                2.3.4.5 stuff
                other stuff 4.4.4.4 things 2022-05-20T22:11:01"
            ),
            'listmatcher-extract-full'
        );
    }

    public function testAddIps(): void {
        $this->matcher->create();
        $this->assertEquals(0, $this->matcher->addIps([]), 'listmatcher-addips-empty');
        $this->assertEquals(2, $this->matcher->addIps(['1.2.3.4', '2.3.4.5', '1.2.3.4']), 'listmatcher-addips-dupe');
    }

    public function testFindCandidates(): void {
        $ipMan = new Manager\IPv4();
        $user = Helper::makeUser('listmatcher', 'listmatcher');
        $this->users[] = $user;
        $user2 = Helper::makeUser('someoneelse', 'listmatcher');
        $this->users[] = $user2;
        $user3 = Helper::makeUser('trackeruser', 'listmatcher');
        $this->users[] = $user3;
        $ips = [
            ['1.2.3.4', new \DateTimeImmutable(), null],
            ['2.3.4.5', new \DateTimeImmutable('2020-02-03 12:34'), new \DateTimeImmutable('2022-02-03 23:34')]
        ];
        $candidate = new UserMatch\MatchCandidate([], ['listmatcher@phpunit.test'], $ips);

        $db = DB::DB();
        // hooray for missing foreign keys
        $db->prepared_query("
            INSERT INTO xbt_snatched
                   (fid, uid, tstamp,                IP, seedtime)
            VALUES (123,   ?, unix_timestamp(now()),  ?, 1)
            ", $user3->id, $ips[1][0]
        );

        $this->matcher->create();

        $this->assertEmpty($this->matcher->findCandidates($candidate, false), 'listmatcher-findcandidates-strict-nomatch');
        $this->assertEquals([$user->id], $this->matcher->findCandidates($candidate), 'listmatcher-findcandidates-loose');

        $ipMan->registerApi($user, $ips[0][0]);
        $this->assertEquals([$user->id], $this->matcher->findCandidates($candidate, false), 'listmatcher-findcandidates-strict-ip');

        $ipMan->registerApi($user2, $ips[0][0]);
        $this->assertEqualsCanonicalizing([$user2->id, $user->id], $this->matcher->findCandidates($candidate), 'listmatcher-findcandidates-loose-ip');

        $this->assertEqualsCanonicalizing([$user2->id, $user->id, $user3->id], $this->matcher->findCandidates($candidate, false, true), 'listmatcher-findcandidates-tracker');
    }

    public function testFindUsers(): void {
        $ipMan = new Manager\IPv4();
        $user = Helper::makeUser('listmatcher', 'listmatcher');
        $this->users[] = $user;
        $user2 = Helper::makeUser('someoneelse', 'listmatcher');
        $this->users[] = $user2;
        $user3 = Helper::makeUser('othermatcher', 'listmatcher');
        $this->users[] = $user3;
        $ips = [
            ['1.2.3.4', new \DateTimeImmutable(), null],
            ['2.3.4.5', new \DateTimeImmutable('2020-02-03 12:34'), new \DateTimeImmutable('2022-02-03 23:34')]
        ];
        $candidate = new UserMatch\MatchCandidate([], ['listmatcher@phpunit.test'], $ips);

        $this->matcher->create();
        $ipMan->registerApi($user3, $ips[0][0]);
        $ipMan->registerApi($user, $ips[0][0]);

        $matches = $this->matcher->findUsers($candidate, true, true);
        $this->assertCount(2, $matches, 'listmatcher-findusers-count');
        $this->assertGreaterThan($matches[1]['match']->score(), $matches[0]['match']->score(), 'listmatcher-findusers-scoreorder');
        $this->assertEquals($matches[0]['user']->id, $user->id, 'listmatcher-findusers-userid');
        $this->assertEquals($matches[1]['user']->id, $user3->id, 'listmatcher-findusers-userid2');
    }

    public function testSort(): void {
        $m1 = new MatchResult();
        $m2 = new MatchResult();

        $m1->addNameMatch('test', 'test', Enum\UserMatchQuality::full);
        $m1->addIpMatch('1.2.3.4', new \DateTimeImmutable('2020-02-03 12:34'), new \DateTimeImmutable('2020-02-03 23:34'), Enum\UserMatchQuality::partial);
        $m1->addIpMatch('1.2.3.4', new \DateTimeImmutable('2016-02-03 12:34'), new \DateTimeImmutable('2016-02-03 23:34'), Enum\UserMatchQuality::partial);
        $m2->addIpMatch('1.2.3.4', new \DateTimeImmutable('2018-02-03 12:34'), new \DateTimeImmutable('2018-02-03 23:34'), Enum\UserMatchQuality::partial);

        $list = [['id' => 1, 'match' => $m1], ['id' => 2, 'match' => $m2]];
        UserMatch\ListMatcher::sortMatches($list, Enum\UserMatchSort::score, Enum\Direction::descending);
        $this->assertEquals(1, $list[0]['id'], 'listmatcher-sort-score-desc');
        UserMatch\ListMatcher::sortMatches($list, Enum\UserMatchSort::score, Enum\Direction::ascending);
        $this->assertEquals(2, $list[0]['id'], 'listmatcher-sort-score-asc');
        UserMatch\ListMatcher::sortMatches($list, Enum\UserMatchSort::firstDate, Enum\Direction::descending);
        $this->assertEquals(2, $list[0]['id'], 'listmatcher-sort-firstDate-desc');
        UserMatch\ListMatcher::sortMatches($list, Enum\UserMatchSort::firstDate, Enum\Direction::ascending);
        $this->assertEquals(1, $list[0]['id'], 'listmatcher-sort-firstDate-asc');
        UserMatch\ListMatcher::sortMatches($list, Enum\UserMatchSort::lastDate, Enum\Direction::descending);
        $this->assertEquals(1, $list[0]['id'], 'listmatcher-sort-lastDate-desc');
        UserMatch\ListMatcher::sortMatches($list, Enum\UserMatchSort::lastDate, Enum\Direction::ascending);
        $this->assertEquals(2, $list[0]['id'], 'listmatcher-sort-lastDate-asc');
    }

    public function testCache(): void {
        $user = Helper::makeUser('listmatcher', 'listmatcher');
        $this->users[] = $user;
        $user2 = Helper::makeUser('listmatcher2', 'listmatcher');
        $this->users[] = $user2;

        $ips = [
            ['1.2.3.4', new \DateTimeImmutable(), null],
            ['2.3.4.5', new \DateTimeImmutable('2020-02-03 12:34'), new \DateTimeImmutable('2022-02-03 23:34')]
        ];
        $candidate = new UserMatch\MatchCandidate([], ['listmatcher@phpunit.test'], $ips);

        $token = UserMatch\ListMatcher::cache($candidate, ['somedata'], 'otherdata', $user);
        $this->assertFalse(UserMatch\ListMatcher::fromCache($token, $user2), 'listmatcher-fromcache-baduser');
        $this->assertEquals([['somedata'], 'otherdata', 2, 1], UserMatch\ListMatcher::fromCache($token, $user), 'listmatcher-fromcache-good');
    }
}
