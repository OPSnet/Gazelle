<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class FriendTest extends TestCase {
    protected array $friend;

    public function tearDown(): void {
        foreach ($this->friend as $f) {
            $f->user()->remove();
        }
    }

    public function testFriend(): void {
        $manager = new Gazelle\Manager\User();
        $this->friend = [
            new Gazelle\User\Friend(Helper::makeUser('friend1.' . randomString(6), 'friend1')),
            new Gazelle\User\Friend(Helper::makeUser('friend2.' . randomString(6), 'friend2')),
            new Gazelle\User\Friend(Helper::makeUser('friend3.' . randomString(6), 'friend3')),
        ];

        // in the beginning
        $this->assertEquals(0, $this->friend[0]->total(), 'friend-i-have-no-friends');
        $this->assertCount(0, $this->friend[1]->page($manager, 10, 0), 'friend-empty-page');
        $this->assertCount(0, $this->friend[2]->userList(), 'friend-empty-user-list');
        $this->assertEquals(-1, $this->friend[0]->add($this->friend[0]->user()), 'friend-0-not-self');

        // add a friend
        $this->assertFalse($this->friend[0]->isFriend($this->friend[1]->user()), 'friend-1-not-friend');
        $this->assertEquals(1, $this->friend[0]->add($this->friend[1]->user()), 'friend-1-add-friend');
        $this->assertTrue($this->friend[0]->isFriend($this->friend[1]->user()), 'friend-1-now-friend');
        $this->assertFalse($this->friend[0]->isMutual($this->friend[1]->user()), 'friend-1-not-yet-mutual');
        $this->assertFalse($this->friend[1]->isFriend($this->friend[0]->user()), 'friend-1-not-mutual');

        // comment
        $comment = 'comment ' . randomString();
        $this->assertEquals(1, $this->friend[0]->addComment($this->friend[1]->user(), $comment), 'friend-1-add-friend');

        // get a page
        $page = $this->friend[0]->page($manager, 10, 0);
        $this->assertCount(1, $page, 'friend-page');
        $this->assertEquals(0, $page[$this->friend[1]->user()->id()]['mutual'], 'friend-not-mutual');

        // mutual
        $this->assertEquals(1, $this->friend[1]->add($this->friend[0]->user()), 'friend-0-add-back');
        $this->assertTrue($this->friend[0]->isMutual($this->friend[1]->user()), 'friend-1-is-mutual');
        $this->assertTrue($this->friend[1]->isMutual($this->friend[0]->user()), 'friend-reciprocal');
        $page = $this->friend[0]->page($manager, 10, 0);
        $this->assertEquals(1, $page[$this->friend[1]->user()->id()]['mutual'], 'friend-the-feeling-is-mutual');

        if (getenv('CI') === false) {
            // FIXME: figure out why causes Twig footer() to fail when running in CI
            // FIXME
            $current = (new Gazelle\User\Session($this->friend[0]->user()))->create([
                'keep-logged' => '0',
                'browser'     => ['BrowserVersion' => null, 'OperatingSystemVersion' => null],
                'ipaddr'      => '127.0.0.1',
                'useragent'   => 'phpunit-browser',
            ]);
            Gazelle\Base::setRequestContext(new Gazelle\BaseRequestContext('/friends.php', '127.0.0.1', ''));
            global $SessionID, $Viewer;
            $SessionID = $current['SessionID'];
            $Viewer    = $this->friend[0]->user();

            // render
            $paginator = new Gazelle\Util\Paginator(FRIENDS_PER_PAGE, 1);
            $paginator->setTotal($this->friend[0]->total());
            $html = Gazelle\Util\Twig::factory()->render('user/friend.twig', [
                'list'      => $this->friend[0]->page($manager, $paginator->limit(), $paginator->offset()),
                'paginator' => $paginator,
                'viewer'    => $this->friend[0]->user(),
            ]);
            $this->assertStringContainsString($comment, $html, 'friend-page-comment');
            $this->assertStringContainsString($this->friend[1]->user()->username(), $html, 'friend-page-username');
        }

        // remove
        $this->friend[0]->add($this->friend[2]->user());
        $this->assertEquals(2, $this->friend[0]->total(), 'friend-has-friends');
        $this->assertEquals(1, $this->friend[0]->remove($this->friend[1]->user()), 'friend-unfriend');
    }
}
