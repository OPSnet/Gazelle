<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class StaffBlogTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testStaffBlog(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(6), 'staffblog');
        $this->userList['mod'] = Helper::makeUser('mod.' . randomString(6), 'staffblog');

        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $this->userList['mod']->setField('PermissionID', MOD)->modify();

        $this->assertSame('Moderator', $this->userList['mod']->userclassName(), 'mod-userclass-check');

        $manager = new Manager\StaffBlog();

        $blog = $manager->create($this->userList['admin'], 'phpunit staff blog', 'body text');
        $this->assertSame('phpunit staff blog', $blog->title(), 'staff-blog-title');
        $this->assertSame('body text', $blog->body(), 'staff-blog-text');
        $this->assertSame($this->userList['admin']->id, $blog->userId(), 'staff-blog-user-id');
        $this->assertTrue(Helper::recentDate($blog->created()), 'staff-blog-created');
        $this->assertGreaterThan(time() - 10, $blog->epoch(), 'staff-blog-epoch');

        $location = 'staffblog.php#blog' . $blog->id;
        $this->assertSame($location, $blog->location(), 'staff-blog-location');
        $this->assertSame(SITE_URL . "/$location", $blog->publicLocation(), 'staff-blog-public-location');
        $this->assertSame($location, $blog->url(), 'staff-blog-url');
        $this->assertSame(SITE_URL . "/$location", $blog->publicUrl(), 'staff-blog-public-url');

        $this->assertSame(0, $manager->readBy($this->userList['mod']), 'staff-blog-not-read-by-mod');
        $this->assertGreaterThan(0, $manager->catchup($this->userList['mod']), 'staff-blog-viewed-by-mod');
        $this->assertGreaterThan(0, $manager->readBy($this->userList['mod']), 'staff-blog-now-read-by-mod');

        if (getenv('CI') === false) {
            // FIXME: Why does this fail only during CI
            global $Twig;
            $this->assertStringContainsString($blog->body(),
                $Twig->render('staffblog/list.twig', [
                    'list'   => $manager->blogList(),
                    'viewer' => $this->userList['mod'],
                ]),
                'staff-blog-twig-render'
            );
        }

        $newBody = 'new body ' . randomString();
        $this->assertTrue($blog->setField('Body', $newBody)->modify(), 'staff-blog-modify');
        $this->assertSame($newBody, $blog->body(), 'staff-blog-new-body');

        $list = $manager->blogList();
        $this->assertSame($blog->id, $list[0]->id, 'staff-blog-latest');

        $this->assertSame(1, $blog->remove(), 'staff-blog-removed');
    }
}
