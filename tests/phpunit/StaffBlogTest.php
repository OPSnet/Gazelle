<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class StaffBlogTest extends TestCase {
    public function testStaffBlog(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $mod = Helper::makeUser('mod.' . randomString(6), 'mod')
            ->setField('PermissionID', MOD);
        $mod->modify();
        $this->assertEquals('Moderator', $mod->userclassName(), 'mod-userclass-check');

        $admin   = (new Gazelle\Manager\User)->find('@admin');
        $manager = new Gazelle\Manager\StaffBlog;

        $blog = $manager->create($admin, 'phpunit staff blog', 'body text');
        $this->assertInstanceOf(Gazelle\StaffBlog::class, $blog, 'staff-blog-create');
        $this->assertEquals('phpunit staff blog', $blog->title(), 'staff-blog-title');
        $this->assertEquals('body text', $blog->body(), 'staff-blog-text');
        $this->assertEquals($admin->id(), $blog->userId(), 'staff-blog-user-id');
        $this->assertIsString($blog->created(), 'staff-blog-created');
        $this->assertIsInt($blog->epoch(), 'staff-blog-epoch');

        $location = 'staffblog.php#blog' . $blog->id();
        $this->assertEquals($location, $blog->location(), 'staff-blog-location');
        $this->assertEquals(SITE_URL . "/$location", $blog->publicLocation(), 'staff-blog-public-location');
        $this->assertEquals($location, $blog->url(), 'staff-blog-url');
        $this->assertEquals(SITE_URL . "/$location", $blog->publicUrl(), 'staff-blog-public-url');

        $this->assertEquals(0, $manager->readBy($mod), 'staff-blog-not-read-by-mod');
        $this->assertGreaterThan(0, $manager->catchup($mod), 'staff-blog-viewed-by-mod');
        $this->assertGreaterThan(0, $manager->readBy($mod), 'staff-blog-now-read-by-mod');

        if (getenv('CI') === false) {
            // FIXME: Why does this fail only during CI
            global $Twig;
            $this->assertStringContainsString($blog->body(),
                $Twig->render('staffblog/list.twig', [
                    'list'   => $manager->blogList(),
                    'viewer' => $mod,
                ]),
                'staff-blog-twig-render'
            );
        }

        $newBody = 'new body ' . randomString();
        $this->assertTrue($blog->setField('Body', $newBody)->modify(), 'staff-blog-modify');
        $this->assertEquals($newBody, $blog->body(), 'staff-blog-new-body');

        $list = $manager->blogList();
        $this->assertEquals($blog->id(), $list[0]->id(), 'staff-blog-latest');

        $this->assertEquals(1, $blog->remove(), 'staff-blog-removed');
        $this->assertEquals(1, $mod->remove(), 'mod-removed');
    }
}
