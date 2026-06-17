<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class EmailBlacklistTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('email.' . randomString(10), 'email.blacklist');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testCreate(): void {
        $manager = new Manager\EmailBlacklist();
        $total   = $manager->total();

        $stem    = randomString(10);
        $domain  = "$stem.phpunitmail";
        $email   = randomString(10) . "@$domain";
        $comment = randomString(10) . ' phpunit comment';

        $blacklist = [$manager->create(str_replace('.', '\.', $domain) . '$', $comment, $this->user)];
        $this->assertGreaterThan(0, $blacklist[0], 'email-blacklist-create');
        $this->assertEquals($total + 1, $manager->total(), 'email-blacklist-new-total');
        $this->assertTrue($manager->exists($domain), 'email-blacklist-find-domain');
        $this->assertTrue($manager->exists($email), 'email-blacklist-find-email');

        $manager->setFilterEmail(str_replace('.', '\\\.', $domain));
        $this->assertEquals(1, $manager->total(), 'email-blacklist-filter-domain-filter');

        do {
            $newEmail = randomString(10) . "@$domain";
        } while ($newEmail === $email);

        $blacklist[] = $manager->create(
            '^' . str_replace('.', '\.', $newEmail) . '$',
            $comment,
            $this->user,
        );
        $this->assertEquals(2, $manager->total(), 'email-blacklist-filter-domain-new-filter');
        $this->assertCount(2, $manager->page(3, 0), 'email-blacklist-domain-page');
        // caught by rule for $domain
        $this->assertTrue($manager->exists($newEmail), 'email-blacklist-find-new-email');

        $newComment = "new $comment";
        $manager->setFilterEmail('')->setFilterComment($newComment);
        $this->assertEquals(0, $manager->total(), 'email-blacklist-filter-comment-fail');
        $this->assertEquals(
            1,
            $manager->modify($blacklist[0], $domain, $newComment, $this->user),
            'email-blacklist-modify',
        );

        $this->assertEquals(1, $manager->total(), 'email-blacklist-filter-comment-true');
        $this->assertCount(1, $manager->page(2, 0), 'email-blacklist-comment-page');

        foreach ($blacklist as $domain) {
            $this->assertEquals(1, $manager->remove($domain), "email-blacklist-remove-$domain");
        }
    }
}
