<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class TGroupVoteTest extends TestCase {
    protected array $tgroupList;
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('tgvote.' . randomString(10), 'vote'),
            Helper::makeUser('tgvote.' . randomString(10), 'vote'),
            Helper::makeUser('tgvote.' . randomString(10), 'vote'),
        ];
        $this->userList[0]->requestContext()->setViewer($this->userList[0]);
        $this->tgroupList = [
            Helper::makeTGroupMusic(
                name:       'phpunit tgvote ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit tgvote ' . randomString(12)]],
                tagName:    ['top.tag', 'tip.tag'],
                user:       $this->userList[0],
                year:       (int)date('Y') - 2,
            ),
            Helper::makeTGroupMusic(
                name:       'phpunit tgvote ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit tgvote ' . randomString(12)]],
                tagName:    ['top.tag', 'tap.tag'],
                user:       $this->userList[0],
                year:       (int)date('Y') - 1,
            ),
        ];
    }

    public function tearDown(): void {
        foreach ($this->tgroupList as $tgroup) {
            Helper::removeTGroup($tgroup, $this->userList[0]);
        }
        $db = DB::DB();
        $pg = new DB\Pg(PG_RW_DSN);
        foreach ($this->userList as $user) {
            $pg->query('
                with t as (
                    delete from tgroup_vote
                    where id_user = $1
                    returning id_tgroup
                )
                delete from tgroup_vote_summary
                where id_tgroup in (
                    select distinct id_tgroup from t
                )
                ', $user->id
            );
            $db->prepared_query("
                DELETE FROM users_votes WHERE UserID = ?
                ", $user->id
            );
            Helper::removeUser($user);
        }
    }

    public function testTGroupVote(): void {
        global $Cache;
        $Cache->delete_multi([
            User\Vote::VOTE_RANK_OVERALL,
            sprintf(User\Vote::VOTE_RANK_DECADE, (int)($this->tgroupList[0]->year() - ($this->tgroupList[0]->year() % 10))),
            sprintf(User\Vote::VOTE_RANK_YEAR, $this->tgroupList[0]->year()),
            "top10_votes_",
        ]);

        $vote = array_map(fn ($u) => new User\Vote($u), $this->userList);
        $n = 0;
        foreach ($vote as $v) {
            $v->upvote($this->tgroupList[0]);
            if ($n++ % 2) {
                $v->upvote($this->tgroupList[1]);
            }
        }
        $this->assertEquals(3, $vote[0]->total($this->tgroupList[0]), 'tgroup-vote-total-all');
        $this->assertEquals(3, $vote[0]->totalUp($this->tgroupList[0]), 'tgroup-vote-total-up');
        $this->assertEquals(0, $vote[0]->totalDown($this->tgroupList[0]), 'tgroup-vote-total-down');
        $this->assertEquals(1, $vote[0]->rankOverall($this->tgroupList[0]), 'tgroup-vote-rank-overall');
        $this->assertEquals(1, $vote[0]->rankYear($this->tgroupList[0]), 'tgroup-vote-rank-year');
        $this->assertEquals(1, $vote[0]->rankDecade($this->tgroupList[0]), 'tgroup-vote-rank-decade');
        $this->assertCount(3, $vote[0]->ranking($this->tgroupList[0], true), 'tgroup-vote-ranking');

        $top = $vote[0]->topVotes();
        $this->assertEquals(1, $top[$this->tgroupList[0]->id]['tier'], 'tgroup-vote-top-1');
        $this->assertEquals(2, $top[$this->tgroupList[1]->id]['tier'], 'tgroup-vote-top-2');
        $this->assertEquals(1, $top[$this->tgroupList[1]->id]['upvote'], 'tgroup-vote-up-2');
        $this->assertCount(2, $vote[1]->userVotes(), 'tgroup-vote-user-count');

        $manager = new Manager\TGroup();
        $this->assertEquals(
            [$this->tgroupList[0]->id],
            array_map(
                fn ($t) => $t->id,
                $manager->similarVote($this->tgroupList[1]),
            ),
            'tgroup-vote-similar',
        );
        $this->assertEquals(
            $this->tgroupList[0]->id,
            $vote[0]->recent()[0]['group_id'],
            'tgroup-vote-recent',
        );
    }

    public function testTGroupDownVote(): void {
        global $Cache;
        $Cache->delete_value("top10_votes_");

        $vote = array_map(fn ($u) => new User\Vote($u), $this->userList);
        $this->assertEquals(0.0, $vote[0]->score($this->tgroupList[0]), 'tg-vote-0-0');
        $result = $vote[0]->upvote($this->tgroupList[0]);
        $this->assertTrue($result[0], 'tg-upvote-result');
        $this->assertEquals("voted", $result[1], 'tg-upvote-text');
        $this->assertStringContainsString(
            "<a href=\"#\" data-id=\"{$this->tgroupList[0]->id}\" class=\"tooltip small_upvote hidden\" title=\"Upvote\">",
            $vote[0]->links($this->tgroupList[0]),
            'tgroup-vote-link'
        );
        $this->assertEqualsWithDelta(0.378382, $vote[0]->score($this->tgroupList[0]), 0.000001, 'tg-vote-1-1');

        $result = $vote[0]->upvote($this->tgroupList[0]);
        $this->assertFalse($result[0], 'tg-reupvote-result');
        $this->assertEquals("already-voted", $result[1], 'tg-reupvote-text');

        $result = $vote[0]->clear($this->tgroupList[0]);
        $this->assertTrue($result[0], 'tg-reupvote-result');
        $this->assertEquals("cleared", $result[1], 'tg-clear');

        $vote[0]->upvote($this->tgroupList[0]);
        $vote[1]->upvote($this->tgroupList[0]);
        $vote[2]->downvote($this->tgroupList[0]);
        $this->assertEqualsWithDelta(0.321145, $vote[0]->score($this->tgroupList[0]), 0.000001, 'tg-vote-1-1');

        $top = $vote[0]->topVotes();
        $this->assertEquals(2, $top[$this->tgroupList[0]->id]['upvote'], 'tgroup-downvote-top-up');
        $this->assertEquals(3, $top[$this->tgroupList[0]->id]['total'], 'tgroup-downvote-top-total');
        $this->assertEquals(3, $vote[0]->total($this->tgroupList[0]), 'tgroup-downvote-all');
        $this->assertEquals(2, $vote[0]->totalUp($this->tgroupList[0]), 'tgroup-downvote-total-up');
        $this->assertEquals(1, $vote[0]->totalDown($this->tgroupList[0]), 'tgroup-downvote-total-down');

        $this->assertEquals(
            ['upvote', 'total', 'score'],
            array_keys($vote[0]->tgroupInfo($this->tgroupList[0])),
            'tgroup-downvote-tgroup-info'
        );

        $page = $vote[0]->userPage(new Manager\TGroup(), User\Vote::UPVOTE, 10, 0);
        $first = current($page);
        $this->assertEquals(
            ['group_id', 'upvote', 'tgroup'],
            array_keys($first),
            'tgroup-downvote-user-page-first',
        );
        $this->assertEquals(
            $this->tgroupList[0]->id,
            $first['group_id'],
            'tgroup-downvote-user-page-id',
        );
    }

    public function testTGroupVoteFilterYear(): void {
        foreach (array_map(fn ($u) => new User\Vote($u), $this->userList) as $v) {
            $v->upvote($this->tgroupList[0]);
            $v->upvote($this->tgroupList[1]);
        }
        $voter = new User\Vote($this->userList[0]);
        $voter->setTopYearInterval((int)date('Y') - 2, (int)date('Y') - 1);
        $this->assertEqualsCanonicalizing(
            array_map(fn ($t) => $t->id, $this->tgroupList),
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-upper-lower',
        );
        $voter = new User\Vote($this->userList[0]);
        $voter->setTopYearInterval((int)date('Y') - 2, 0);
        $this->assertNotContains(
            [$this->tgroupList[0]->id],
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-lower',
        );
        $voter = new User\Vote($this->userList[0]);
        $voter->setTopYearInterval(0, (int)date('Y') - 1);
        $this->assertNotContains(
            [$this->tgroupList[1]->id],
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-upper',
        );
    }

    public function testTGroupVoteFilterTag(): void {
        foreach (array_map(fn ($u) => new User\Vote($u), $this->userList) as $v) {
            $v->upvote($this->tgroupList[0]);
            $v->upvote($this->tgroupList[1]);
        }

        $voter = new User\Vote($this->userList[0]);
        $voter->setTopTagList(['top.tag'], all: true);
        $this->assertEqualsCanonicalizing(
            array_map(fn ($t) => $t->id, $this->tgroupList),
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-tag-all-general',
        );

        $voter = new User\Vote($this->userList[0]);
        $voter->setTopTagList(['top.tag', 'tip.tag'], all: true);
        $this->assertEqualsCanonicalizing(
            [$this->tgroupList[0]->id],
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-tag-all-specific',
        );

        $voter = new User\Vote($this->userList[0]);
        $voter->setTopTagList(['tap.tag', 'tip.tag'], all: true);
        $this->assertEquals(
            [],
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-tag-any-none',
        );

        $voter = new User\Vote($this->userList[0]);
        $voter->setTopTagList(['tap.tag', 'tip.tag'], all: false);
        $this->assertEquals(
            array_map(fn ($t) => $t->id, $this->tgroupList),
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-tag-any-some',
        );

        $voter = new User\Vote($this->userList[0]);
        $voter->setTopTagList(['tap.tag', 'tip.tag'], all: false);
        $voter->setTopLimit(1);
        $this->assertCount(
            1,
            array_keys($voter->topVotes(bypassCache: true)),
            'tgroup-vote-filter-limit',
        );
    }
}
