<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class TGroupVoteTest extends TestCase {
    protected array $tgroupList;
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('tgvote.' . randomString(10), 'vote'),
            \GazelleUnitTest\Helper::makeUser('tgvote.' . randomString(10), 'vote'),
            \GazelleUnitTest\Helper::makeUser('tgvote.' . randomString(10), 'vote'),
        ];
        $this->tgroupList = [
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'phpunit tgvote ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit tgvote ' . randomString(12)]],
                tagName:    ['jazz'],
                user:       $this->userList[0],
            ),
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'phpunit tgvote ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit tgvote ' . randomString(12)]],
                tagName:    ['metal'],
                user:       $this->userList[0],
            ),
        ];
    }

    public function tearDown(): void {
        foreach ($this->tgroupList as $tgroup) {
            \GazelleUnitTest\Helper::removeTGroup($tgroup, $this->userList[0]);
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testTGroupVote(): void {
        global $Cache;
        $Cache->delete_multi([
            "voting_ranks_overall",
            "voting_ranks_year_{$this->tgroupList[0]->year()}",
            "voting_ranks_decade_" . ($this->tgroupList[0]->year() - ($this->tgroupList[0]->year() % 10)),
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
        $this->assertEquals(1, $top[$this->tgroupList[0]->id()]['sequence'], 'tgroup-vote-top-1');
        $this->assertEquals(2, $top[$this->tgroupList[1]->id()]['sequence'], 'tgroup-vote-top-2');
        $this->assertEquals(1, $top[$this->tgroupList[1]->id()]['Ups'], 'tgroup-vote-up-2');

        $this->assertCount(2, $vote[1]->userVotes(), 'tgroup-vote-user-count');

        // at least check the SQL
        $manager = new Manager\TGroup();
        $this->assertCount(0, $manager->similarVote($this->tgroupList[1]), 'tgroup-vote-similar');
    }

    public function testTGroupDownVote(): void {
        global $Cache;
        $Cache->delete_value("top10_votes_");

        $vote = array_map(fn ($u) => new User\Vote($u), $this->userList);
        $this->assertEquals(0, $vote[0]->score($this->tgroupList[0]), 'tg-vote-0-0');
        $result = $vote[0]->upvote($this->tgroupList[0]);
        $this->assertTrue($result[0], 'tg-upvote-result');
        $this->assertEquals("voted", $result[1], 'tg-upvote-text');
        $this->assertStringContainsString("id=\"vote_up_{$this->tgroupList[0]->id()}\"", $vote[0]->links($this->tgroupList[0]), 'tgroup-vote-link');
        $this->assertEquals(0.37838, round($vote[0]->score($this->tgroupList[0]), 5), 'tg-vote-1-1');

        $result = $vote[0]->upvote($this->tgroupList[0]);
        $this->assertFalse($result[0], 'tg-reupvote-result');
        $this->assertEquals("already-voted", $result[1], 'tg-reupvote-text');

        $result = $vote[0]->clear($this->tgroupList[0]);
        $this->assertTrue($result[0], 'tg-reupvote-result');
        $this->assertEquals("cleared", $result[1], 'tg-clear');

        $vote[0]->upvote($this->tgroupList[0]);
        $vote[1]->upvote($this->tgroupList[0]);
        $vote[2]->downvote($this->tgroupList[0]);
        $this->assertEquals(0.32115, round($vote[0]->score($this->tgroupList[0]), 5), 'tg-vote-3-1');

        $top = $vote[0]->topVotes();
        $this->assertEquals(2, $top[$this->tgroupList[0]->id()]['Ups'], 'tgroup-downvote-top-up');
        $this->assertEquals(3, $top[$this->tgroupList[0]->id()]['Total'], 'tgroup-downvote-top-total');
        $this->assertEquals(3, $vote[0]->total($this->tgroupList[0]), 'tgroup-downvote-all');
        $this->assertEquals(2, $vote[0]->totalUp($this->tgroupList[0]), 'tgroup-downvote-total-up');
        $this->assertEquals(1, $vote[0]->totalDown($this->tgroupList[0]), 'tgroup-downvote-total-down');

        $this->assertEquals(['Ups', 'Total', 'Score'], array_keys($vote[0]->tgroupInfo($this->tgroupList[0])), 'tgroup-downvote-tgroup-info');
    }
}
