<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class EmailBlacklistTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('email.' . randomString(10), 'email.blacklist');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testCreate(): void {
        $manager = new Manager\EmailBlacklist();
        $total   = $manager->total();

        $stem = randomString(10);
        $domain = "$stem.phpunitmail";
        $comment = randomString(10) . ' phpunit comment';

        $blacklist = [$manager->create("$stem\\.phpunitmail" . '$', $comment, $this->user)];
        $this->assertGreaterThan(0, $blacklist[0], 'email-blacklist-create');
        $this->assertEquals($total + 1, $manager->total(), 'email-blacklist-new-total');
        $this->assertTrue($manager->exists("someone@$domain"), 'email-find-someone');

        $manager->setFilterEmail($stem);
        $this->assertEquals(1, $manager->total(), 'email-blacklist-filter-domain-filter');

        $newRegexp = "new-$stem\\.phpunitmail" . '$';
        $blacklist[] = $manager->create($newRegexp, $comment, $this->user);
        $this->assertEquals(2, $manager->total(), 'email-blacklist-filter-domain-new-filter');
        $this->assertCount(2, $manager->page(3, 0), 'email-blacklist-domain-page');

        $newComment = "new $comment";
        $manager->setFilterEmail('')->setFilterComment($newComment);
        $this->assertEquals(0, $manager->total(), 'email-blacklist-filter-comment-fail');
        $this->assertEquals(1, $manager->modify($blacklist[0], $domain, $newComment, $this->user), 'email-blacklist-modify');

        $this->assertEquals(1, $manager->total(), 'email-blacklist-filter-comment-true');
        $this->assertCount(1, $manager->page(2, 0), 'email-blacklist-comment-page');

        foreach ($blacklist as $domain) {
            $this->assertEquals(1, $manager->remove($domain), "email-blacklist-remove-$domain");
        }
    }
}
