<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class StaffBlogTest extends TestCase {
    public function testStaffBlog() {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $mod = (new Gazelle\UserCreator)
            ->setUsername('mod.' . randomString(6))
            ->setEmail(randomString(6) . "@mod.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setAdminComment('Created by tests/phpunit/StaffBlogTest.php')
            ->create()
            ->setUpdate('PermissionID', MOD);
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
        $this->assertTrue($blog->setUpdate('Body', $newBody)->modify(), 'staff-blog-modify');
        $this->assertEquals($newBody, $blog->body(), 'staff-blog-new-body');

        $list = $manager->blogList();
        $this->assertEquals($blog->id(), $list[0]->id(), 'staff-blog-latest');

        $this->assertEquals(1, $blog->remove(), 'staff-blog-removed');
        $this->assertEquals(1, $mod->remove(), 'mod-removed');
    }
}
