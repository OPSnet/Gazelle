<?php

namespace Gazelle;

use Gazelle\Enum\UserMatchQuality;
use Gazelle\UserMatch\MatchCandidate;
use Gazelle\UserMatch\MatchResult;
use PHPUnit\Framework\TestCase;

class MatchCandidateTest extends TestCase {
    protected MatchCandidate $mc;

    public function setUp(): void {
        $this->mc = new MatchCandidate(['cand1', 'match'], ['some@email.example', 'cand1@test.domain'], [
            ['1.2.3.4', new \DateTimeImmutable(), new \DateTimeImmutable()],
            ['1.2.3.4', new \DateTimeImmutable('2018-03-04 12:34'), new \DateTimeImmutable('2018-05-04 22:43')],
            ['2.3.4.5', new \DateTimeImmutable('2020-03-04 12:34'), null]
        ]);
    }

    public function testMatchSelf(): void {
        $this->assertEqualsCanonicalizing(['1.2.3.4', '2.3.4.5'], array_keys($this->mc->keyedIps()), 'matchcandidate-keyedips');
        $result = $this->mc->match($this->mc);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-self-matches');
        $this->assertEqualsCanonicalizing([
                ['cand1', 'cand1', UserMatchQuality::full],
                ['match', 'match', UserMatchQuality::full],
                ['cand1', 'cand1@test.domain', UserMatchQuality::partial],
                ['cand1@test.domain', 'cand1', UserMatchQuality::partial]
            ], $result->usernames(), 'matchcandidate-self-usernames'
        );
        $this->assertEqualsCanonicalizing([
                ['some@email.example', 'some@email.example', UserMatchQuality::full],
                ['cand1@test.domain', 'cand1@test.domain', UserMatchQuality::full]
            ], $result->emails(), 'matchcandidate-self-emails'
        );
        $this->assertCount(3, $result->ips(), 'matchcandidate-self-ips');
        $this->assertSame(400, $result->score(), 'matchcandidate-self-score');
        $this->assertSame([
                'usernames' => [UserMatchQuality::full->value => 2, UserMatchQuality::partial->value => 2],
                'emails' => [UserMatchQuality::full->value => 2],
                'ips' => [UserMatchQuality::full->value => 3]
            ], $result->summary(), 'matchcandidate-self-summary'
        );
    }

    public function testMatchNamesEmails(): void {
        $c2 = new MatchCandidate(['cand1'], ['someother@email.example'], []);
        $result = new MatchResult();
        $this->mc->matchNamesEmails($c2, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchNamesEmails-matches');
        $this->assertSame(['usernames' => [UserMatchQuality::partial->value => 1]],
            $result->summary(), 'matchcandidate-matchNamesEmails-summary');
        $this->assertSame([['cand1@test.domain', 'cand1', UserMatchQuality::partial]],
            $result->usernames(), 'matchcandidate-matchNamesEmails-usernames');
    }

    public function testMatchNames(): void {
        $c2 = new MatchCandidate(['cand1'], [], []);
        $result = new MatchResult();
        $this->mc->matchNames($c2, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchNames-matches');
        $this->assertSame(['usernames' => [UserMatchQuality::full->value => 1]],
            $result->summary(), 'matchcandidate-matchNames-summary');

        $c3 = new MatchCandidate(['cand2'], [], []);
        $result = new MatchResult();
        $this->mc->matchNames($c3, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchNames-matches2');
        $this->assertSame(['usernames' => [UserMatchQuality::partial->value => 1]],
            $result->summary(), 'matchcandidate-matchNames-summary2');

        $c4 = new MatchCandidate(['dude'], [], []);
        $result = new MatchResult();
        $this->mc->matchNames($c4, $result);
        $this->assertFalse($result->hasMatch(), 'matchcandidate-matchNames-nomatch');
    }

    public function testMatchEmails(): void {
        $c2 = new MatchCandidate([], ['some@email.example'], []);
        $result = new MatchResult();
        $this->mc->matchEmails($c2, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchEmails-matches');
        $this->assertSame(['emails' => [UserMatchQuality::full->value => 1]],
            $result->summary(), 'matchcandidate-matchEmails-summary');

        $c3 = new MatchCandidate([], ['some1@email.example'], []);
        $result = new MatchResult();
        $this->mc->matchEmails($c3, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchEmails-matches2');
        $this->assertSame(['emails' => [UserMatchQuality::weak->value => 1]],
            $result->summary(), 'matchcandidate-matchEmails-summary2');

        $c4 = new MatchCandidate([], ['e@b.c'], []);
        $result = new MatchResult();
        $this->mc->matchEmails($c4, $result);
        $this->assertFalse($result->hasMatch(), 'matchcandidate-matchEmails-nomatch');
    }

    public function testMatchIps(): void {
        $c2 = new MatchCandidate([], [], [['1.2.3.4', new \DateTimeImmutable(), null]]);
        $result = new MatchResult();
        $this->mc->matchIps($c2, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchIps-matches');
        $this->assertSame(['ips' => [UserMatchQuality::full->value => 1]],
            $result->summary(), 'matchcandidate-matchIps-summary');

        $c3 = new MatchCandidate([], [], [['1.2.3.4', new \DateTimeImmutable('2018-04-04 12:34'), new \DateTimeImmutable('2018-06-04 22:43')]]);
        $result = new MatchResult();
        $this->mc->matchIps($c3, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchIps-matches2');
        $this->assertSame(['ips' => [UserMatchQuality::partial->value => 1]],
            $result->summary(), 'matchcandidate-matchIps-summary2');

        $c4 = new MatchCandidate([], [], [['1.2.3.4', null, null]]);
        $result = new MatchResult();
        $this->mc->matchIps($c4, $result);

        $this->assertTrue($result->hasMatch(), 'matchcandidate-matchIps-matches3');
        $this->assertSame(['ips' => [UserMatchQuality::weak->value => 1]],
            $result->summary(), 'matchcandidate-matchIps-summary3');

        $c5 = new MatchCandidate([], [], [['6.2.3.4', new \DateTimeImmutable(), new \DateTimeImmutable()]]);
        $result = new MatchResult();
        $this->mc->matchIps($c5, $result);
        $this->assertFalse($result->hasMatch(), 'matchcandidate-matchIps-nomatch');
    }

    public function testCleanupEmail(): void {
        $this->assertSame(['te.st', 'test.domain'], $this->mc->cleanupEmail('te.st+1@test.domain'), 'matchcandidate-cleanupemail-basic');
        $this->assertSame(['te.st', 'proton.me'], $this->mc->cleanupEmail('te.st@pm.me'), 'matchcandidate-cleanupemail-proton');
        $this->assertSame(['test', 'gmail.com'], $this->mc->cleanupEmail('t.e.s.t+abc1@googlemail.com'), 'matchcandidate-cleanupemail-gmail');
    }
}
