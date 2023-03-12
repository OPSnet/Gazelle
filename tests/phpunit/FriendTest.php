<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class FriendTest extends TestCase {
    public function testFriend(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $manager = new Gazelle\Manager\User;
        $creator = new Gazelle\UserCreator;

        $friend = [
            new Gazelle\User\Friend(
                $creator->setUsername('friend1.' . randomString(6))
                    ->setEmail(randomString(6) . "@friend1.example.com")
                    ->setPassword(randomString())
                    ->setIpaddr('127.0.0.1')
                    ->setAdminComment('Created by tests/phpunit/InviteTest.php')
                    ->create()
                ),
            new Gazelle\User\Friend(
                $creator->setUsername('friend2.' . randomString(6))
                    ->setEmail(randomString(6) . "@friend2.example.com")
                    ->setPassword(randomString())
                    ->setIpaddr('127.0.0.1')
                    ->setAdminComment('Created by tests/phpunit/InviteTest.php')
                    ->create()
                ),
            new Gazelle\User\Friend(
                $creator->setUsername('friend3.' . randomString(6))
                    ->setEmail(randomString(6) . "@friend3.example.com")
                    ->setPassword(randomString())
                    ->setIpaddr('127.0.0.1')
                    ->setAdminComment('Created by tests/phpunit/InviteTest.php')
                    ->create()
                ),
        ];

        // in the beginning
        $this->assertEquals(0, $friend[0]->total(), 'friend-i-have-no-friends');
        $this->assertCount(0, $friend[1]->page($manager, 10, 0), 'friend-empty-page');
        $this->assertCount(0, $friend[2]->userList(), 'friend-empty-user-list');

        // add a friend
        $this->assertFalse($friend[0]->isFriend($friend[1]->user()->id()), 'friend-1-not-friend');
        $this->assertEquals(1, $friend[0]->add($friend[1]->user()->id()), 'friend-1-add-friend');
        $this->assertTrue($friend[0]->isFriend($friend[1]->user()->id()), 'friend-1-now-friend');
        $this->assertFalse($friend[0]->isMutual($friend[1]->user()->id()), 'friend-1-not-yet-mutual');
        $this->assertFalse($friend[1]->isFriend($friend[0]->user()->id()), 'friend-1-not-mutual');

        // comment
        $comment = 'comment ' . randomString();
        $this->assertEquals(1, $friend[0]->addComment($friend[1]->user()->id(), $comment), 'friend-1-add-friend');

        // get a page
        $page = $friend[0]->page($manager, 10, 0);
        $this->assertCount(1, $page, 'friend-page');
        $this->assertEquals(0, $page[$friend[1]->user()->id()]['mutual'], 'friend-not-mutual');

        // mutual
        $this->assertEquals(1, $friend[1]->add($friend[0]->user()->id()), 'friend-0-add-back');
        $this->assertTrue($friend[0]->isMutual($friend[1]->user()->id()), 'friend-1-is-mutual');
        $this->assertTrue($friend[1]->isMutual($friend[0]->user()->id()), 'friend-reciprocal');
        $page = $friend[0]->page($manager, 10, 0);
        $this->assertEquals(1, $page[$friend[1]->user()->id()]['mutual'], 'friend-the-feeling-is-mutual');

        if (getenv('CI') === false) {
            // FIXME: figure out why causes Twig footer() to fail when running in CI
            // FIXME
            $current = (new Gazelle\User\Session($friend[0]->user()))->create([
                'keep-logged' => '0',
                'browser'     => ['BrowserVersion' => null, 'OperatingSystemVersion' => null],
                'ipaddr'      => '127.0.0.1',
                'useragent'   => 'phpunit-browser',
            ]);
            global $Document, $SessionID, $Viewer;
            $Document  = 'friends';
            $SessionID = $current['SessionID'];
            $Viewer    = $friend[0]->user();

            // render
            $paginator = new Gazelle\Util\Paginator(FRIENDS_PER_PAGE, 1);
            $paginator->setTotal($friend[0]->total());
            $html = Gazelle\Util\Twig::factory()->render('user/friend.twig', [
                'list'      => $friend[0]->page($manager, $paginator->limit(), $paginator->offset()),
                'paginator' => $paginator,
                'viewer'    => $friend[0]->user(),
            ]);
            $this->assertStringContainsString($comment, $html, 'friend-page-comment');
            $this->assertStringContainsString($friend[1]->user()->username(), $html, 'friend-page-username');
        }

        // remove
        $friend[0]->add($friend[2]->user()->id());
        $this->assertEquals(2, $friend[0]->total(), 'friend-has-friends');
        $this->assertEquals(1, $friend[0]->remove($friend[1]->user()->id()), 'friend-unfriend');

        foreach (array_keys($friend) as $n) {
            $this->assertEquals(1, $friend[$n]->user()->remove(), "friend-remove-$n");
        }
    }
}
