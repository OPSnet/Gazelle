<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class WikiTest extends TestCase {
    protected array $userList;
    protected array $articleList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('wiki.' . randomString(6), 'wiki'),
            'user'  => Helper::makeUser('wiki.' . randomString(6), 'wiki'),
        ];
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        if (isset($this->articleList)) {
            foreach ($this->articleList as $article) {
                $article->remove();
            }
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public static function providerAlias(): array {
        return [
            ['alias', 'alias  ', 'wiki-clean-alias-trim'],
            ['alias', 'a.l=i+a-s', 'wiki-clean-alias-regexp'],
            ['alias', 'ALIAS', 'wiki-clean-alias-case'],
            ['alias', ' A,LIaS. ', 'wiki-clean-alias-all'],
        ];
    }

    #[DataProvider('providerAlias')]
    public function testNormalizeAlias(string $expected, string $input, string $message): void {
        $this->assertEquals($expected, \Gazelle\Wiki::normalizeAlias($input), $message);
    }

    public function testWikiCreate(): void {
        $manager = new \Gazelle\Manager\Wiki;
        $title   = 'phpunit title ' . randomString(6);
        $alias   = \Gazelle\Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->assertInstanceOf(\Gazelle\Wiki::class, $article, 'wiki-create-open');

        $this->assertEquals($article->id(), $manager->findById($article->id())->id(), 'wiki-find-by-id');
        $this->assertEquals($article->id(), $manager->findByTitle($article->title())->id(), 'wiki-find-by-title');

        $this->assertInstanceOf(\Gazelle\Wiki::class, $article->flush(), 'wiki-flush');
        $this->assertEquals("<a href=\"{$article->url()}\">{$article->title()}</a>", $article->link(), 'wiki-link');
        $this->assertEquals("wiki.php?action=article&id={$article->id()}", $article->location(), 'wiki-location');
        $this->assertEquals($alias, array_keys($article->alias())[0], 'wiki-alias');
        $this->assertEquals('wiki body', $article->body(), 'wiki-body');
        $this->assertStringStartsWith(date('Y-m-d H'), $article->date(), 'wiki-date');
        $this->assertEquals($title, $article->title(), 'wiki-title');
        $this->assertEquals($title, $article->shortName($title), 'wiki-short-name');
        $this->assertEquals($this->userList['admin']->id(), $article->authorId(), 'wiki-author-id');
        $this->assertEquals($this->userList['user']->privilege()->effectiveClassLevel(), $article->minClassRead(), 'wiki-min-read');
        $this->assertEquals($this->userList['user']->privilege()->effectiveClassLevel(), $article->minClassEdit(), 'wiki-min-edit');

        $this->assertTrue($article->editable($this->userList['user']), 'wiki-edit-user');
        $this->assertTrue($article->editable($this->userList['admin']), 'wiki-edit-sysop');
        $this->assertStringStartsWith('<ol', $article->ToC(), 'wiki-toc');

        $this->assertEquals(1, $article->remove(), 'wiki-remove-open');
    }

    public function testWikiAlias(): void {
        $manager = new \Gazelle\Manager\Wiki;
        $title   = 'phpunit title ' . randomString(6);
        $alias   = \Gazelle\Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->articleList[] = $article;

        $newAlias = \Gazelle\Wiki::normalizeAlias('alias' . randomString(20));
        $this->assertEquals(0, $article->removeAlias($newAlias), 'wiki-remove-missing-alias');
        $this->assertEquals(1, $article->addAlias($newAlias, $this->userList['admin']), 'wiki-add-alias');
        $this->assertCount(2, $article->alias(), 'wiki-alias-list');
        $this->assertEquals($article->id(), $manager->findByAlias($newAlias)->id(), 'wiki-find-by-alias');
        $this->assertEquals(1, $article->removeAlias($newAlias), 'wiki-remove-alias');
    }

    public function testWikiException(): void {
        $manager = new \Gazelle\Manager\Wiki;
        $title   = 'phpunit title ' . randomString(6);
        $alias   = \Gazelle\Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->articleList[] = $article;

        $newAlias = \Gazelle\Wiki::normalizeAlias('alias' . randomString(20));
        $this->assertEquals(1, $article->addAlias($newAlias, $this->userList['admin']), 'wiki-add-ok-alias');

        $this->expectException(\Gazelle\DB\MysqlDuplicateKeyException::class);
        $article->addAlias($newAlias, $this->userList['admin']);
    }

    public function testWikiList(): void {
        $classList = (new \Gazelle\Manager\User)->classList();
        $level = [
            USER  => $classList[USER]['Level'],
            SYSOP => $classList[SYSOP]['Level'],
        ];
        $manager = new \Gazelle\Manager\Wiki;
        $initial = [
            USER  => count($manager->articles($level[USER])),
            SYSOP => count($manager->articles($level[SYSOP])),
        ];
        $this->articleList[] = $manager->create('z phpunit ' . randomString(6), 'wiki body', $level[USER], $level[USER], $this->userList['admin']);
        $this->articleList[] = $manager->create('x phpunit ' . randomString(6), 'wiki body', $level[USER], $level[USER], $this->userList['admin']);
        $this->articleList[] = $manager->create('x phpunit sysop ' . randomString(6), 'wiki body', $level[SYSOP], $level[SYSOP], $this->userList['admin']);

        $this->assertFalse($this->articleList[2]->editable($this->userList['user']), 'wiki-no-edit-user');
        $this->assertCount(2 + $initial[USER], $manager->articles($level[USER]), 'wiki-list-user-all');
        $this->assertCount(2 + $initial[USER], $manager->articles($level[USER], '1'), 'wiki-list-user-1');
        $this->assertCount(3 + $initial[SYSOP], $manager->articles($level[SYSOP]), 'wiki-list-sysop-all');
        // This will fail locally if any existing articles begin with 'x'
        $this->assertCount(1, $manager->articles($level[USER], 'x'), 'wiki-list-user-x');
        $this->assertCount(2, $manager->articles($level[SYSOP], 'x'), 'wiki-list-sysop-x');
    }

    public function testWikiRevision(): void {
        $manager = new \Gazelle\Manager\Wiki;
        $title   = 'phpunit title ' . randomString(6);
        $alias   = \Gazelle\Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->articleList[] = $article;

        $this->assertCount(0, $article->revisionList(), 'wiki-no-revision');
        $this->assertTrue($article->setField('Body', 'wiki body edit')->modify(), 'wiki-edit-body');
        $this->assertCount(1, $article->revisionList(), 'wiki-revision');

        $clone = $manager->findById($article->id());
        $this->assertEquals(2, $clone->revision(), 'wiki-n-revision');
        $this->assertEquals('wiki body', $clone->revisionBody(1), 'wiki-body-revision');
    }

    public function testConfigureAccess(): void {
        $manager = new \Gazelle\Manager\Wiki;
        $access = $manager->configureAccess(
            user:    $this->userList['user'],
            minRead: $this->userList['user']->privilege()->effectiveClassLevel(),
            minEdit: $this->userList['admin']->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                $this->userList['user']->privilege()->effectiveClassLevel(),
                $this->userList['user']->privilege()->effectiveClassLevel(),
                null,
            ],
            $access,
            'wiki-access-user'
        );

        $this->userList['user']->addCustomPrivilege('admin_manage_wiki');
        $this->userList['user']->modify();

        $access = $manager->configureAccess(
            user:    $this->userList['user'],
            minRead: 0,
            minEdit: $this->userList['user']->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                null,
                null,
                'read permission not set',
            ],
            $access,
            'wiki-access-no-read'
        );

        $access = $manager->configureAccess(
            user:    $this->userList['user'],
            minRead: $this->userList['user']->privilege()->effectiveClassLevel(),
            minEdit: 0,
        );
        $this->assertEquals(
            [
                null,
                null,
                'edit permission not set',
            ],
            $access,
            'wiki-access-no-read'
        );

        $access = $manager->configureAccess(
            user:    $this->userList['user'],
            minRead: $this->userList['admin']->privilege()->effectiveClassLevel(),
            minEdit: $this->userList['user']->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                null,
                null,
                'You cannot restrict views above your own level',
            ],
            $access,
            'wiki-access-read-above'
        );

        $access = $manager->configureAccess(
            user:    $this->userList['user'],
            minRead: $this->userList['user']->privilege()->effectiveClassLevel(),
            minEdit: $this->userList['admin']->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                null,
                null,
                'You cannot restrict edits above your own level',
            ],
            $access,
            'wiki-access-edit-above'
        );

        $access = $manager->configureAccess(
            user:    $this->userList['admin'],
            minRead: $this->userList['user']->privilege()->effectiveClassLevel(),
            minEdit: $this->userList['admin']->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                $this->userList['user']->privilege()->effectiveClassLevel(),
                $this->userList['admin']->privilege()->effectiveClassLevel(),
                null,
            ],
            $access,
            'wiki-access-edit-above'
        );
    }
}
