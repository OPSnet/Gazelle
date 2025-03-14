<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class WikiTest extends TestCase {
    protected array $userList;
    protected array $articleList;

    public function setUp(): void {
        $this->userList = [
            'admin' => \GazelleUnitTest\Helper::makeUser('wiki.' . randomString(6), 'wiki'),
            'user'  => \GazelleUnitTest\Helper::makeUser('wiki.' . randomString(6), 'wiki'),
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
        $this->assertEquals($expected, Wiki::normalizeAlias($input), $message);
    }

    public function testWikiCreate(): void {
        $manager = new Manager\Wiki();
        $title   = 'phpunit title ' . randomString(6);
        $alias   = Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->assertInstanceOf(Wiki::class, $article, 'wiki-create-open');

        $this->assertEquals($article->id(), $manager->findById($article->id())->id(), 'wiki-find-by-id');
        $this->assertEquals($article->id(), $manager->findByTitle($article->title())->id(), 'wiki-find-by-title');

        $this->assertInstanceOf(Wiki::class, $article->flush(), 'wiki-flush');
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

    public function testBBCodeWiki(): void {
        $manager = new Manager\Wiki();
        $title   = 'phpunit bbwiki ' . randomString(6);
        $alias   = Wiki::normalizeAlias($title);
        $this->userList['user'] = \GazelleUnitTest\Helper::makeUser('wiki.' . randomString(6), 'text');
        $article = $manager->create(
            $title,
            'wiki bbcode body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user'],
        );
        $this->articleList[] = $article;
        \Text::setViewer($this->userList['user']);

        $this->assertEquals(
            "<a href=\"wiki.php\">Wiki</a> › <a href=\"wiki.php?action=article&amp;id={$article->id()}\">$title</a>",
            \Text::full_format(SITE_URL . "/wiki.php?action=article&name=$alias"),
            'text-wiki-name-location',
        );
        $this->assertEquals(
            "<a href=\"wiki.php\">Wiki</a> › <a href=\"wiki.php?action=article&amp;id={$article->id()}\">$title</a>",
            \Text::full_format("{$article->publicLocation()}"),
            'text-wiki-id-location',
        );
        $this->assertEquals(
            "<a href=\"wiki.php\">Wiki</a> › <a href=\"wiki.php?action=article&amp;id={$article->id()}\">$title</a>",
            \Text::full_format("[[{$alias}]]"),
            'wiki-bbcode-alias'
        );
        $this->assertEquals(
            "<a href=\"wiki.php\">Wiki</a> › [[x$alias ???]]",
            \Text::full_format("[[x$alias]]"),
            'wiki-bbcode-bad-alias'
        );
    }

    public function testWikiAlias(): void {
        $manager = new Manager\Wiki();
        $title   = 'phpunit title ' . randomString(6);
        $alias   = Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->articleList[] = $article;

        $newAlias = Wiki::normalizeAlias('alias' . randomString(20));
        $this->assertEquals(0, $article->removeAlias($newAlias), 'wiki-remove-missing-alias');
        $this->assertEquals(1, $article->addAlias($newAlias, $this->userList['admin']), 'wiki-add-alias');
        $this->assertCount(2, $article->alias(), 'wiki-alias-list');
        $this->assertEquals($article->id(), $manager->findByAlias($newAlias)->id(), 'wiki-find-by-alias');
        $this->assertEquals(1, $article->removeAlias($newAlias), 'wiki-remove-alias');
    }

    public function testWikiException(): void {
        $manager = new Manager\Wiki();
        $title   = 'phpunit title ' . randomString(6);
        $alias   = Wiki::normalizeAlias($title);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $this->articleList[] = $article;

        $newAlias = Wiki::normalizeAlias('alias' . randomString(20));
        $this->assertEquals(1, $article->addAlias($newAlias, $this->userList['admin']), 'wiki-add-ok-alias');

        $this->expectException(DB\MysqlDuplicateKeyException::class);
        $article->addAlias($newAlias, $this->userList['admin']);
    }

    public function testWikiList(): void {
        $classList = (new Manager\User())->classList();
        $level = [
            USER  => $classList[USER]['Level'],
            SYSOP => $classList[SYSOP]['Level'],
        ];
        $manager = new Manager\Wiki();
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

    public function testEditReadable(): void {
        $admin   = $this->userList['admin'];
        $user    = $this->userList['user'];
        $manager = new Manager\Wiki();
        $article = $manager->create(
            title:   'wiki edit readable ' . randomString(10),
            body:    'wiki body phpunit',
            minRead: $user->privilege()->effectiveClassLevel(),
            minEdit: $admin->privilege()->effectiveClassLevel(),
            user:    $admin,
        );
        $this->assertFalse($article->editable($user), 'readable article cannot be edited');
        $user->toggleAttr('wiki-edit-readable', true);
        $this->assertTrue($article->editable($user), 'readable article can be edited');
        $user->toggleAttr('wiki-edit-readable', false);
        $this->assertFalse($article->editable($user), 'readable article can no longer be edited');
    }

    public function testWikiRevision(): void {
        $manager = new Manager\Wiki();
        $title   = 'phpunit title ' . randomString(6);
        $alias   = Wiki::normalizeAlias($title);
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
        $manager = new Manager\Wiki();
        $admin = $this->userList['admin'];
        $user  = $this->userList['user'];
        $access = $manager->configureAccess(
            user:    $user,
            minRead: $user->privilege()->effectiveClassLevel(),
            minEdit: $admin->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                $user->privilege()->effectiveClassLevel(),
                $user->privilege()->effectiveClassLevel(),
                null,
            ],
            $access,
            'wiki-access-user'
        );

        $user->addCustomPrivilege('admin_manage_wiki');
        $user->modify();

        $access = $manager->configureAccess(
            user:    $user,
            minRead: 0,
            minEdit: $user->privilege()->effectiveClassLevel(),
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
            user:    $user,
            minRead: $user->privilege()->effectiveClassLevel(),
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
            user:    $user,
            minRead: $admin->privilege()->effectiveClassLevel(),
            minEdit: $user->privilege()->effectiveClassLevel(),
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
            user:    $user,
            minRead: $user->privilege()->effectiveClassLevel(),
            minEdit: $admin->privilege()->effectiveClassLevel(),
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
            user:    $admin,
            minRead: $user->privilege()->effectiveClassLevel(),
            minEdit: $admin->privilege()->effectiveClassLevel(),
        );
        $this->assertEquals(
            [
                $user->privilege()->effectiveClassLevel(),
                $admin->privilege()->effectiveClassLevel(),
                null,
            ],
            $access,
            'wiki-access-edit-above'
        );
    }

    public function testWikiSelfrefRegression(): void {
        $manager = new Manager\Wiki();
        $title   = 'phpunit title ' . randomString(6);
        $article = $manager->create(
            $title,
            'wiki body',
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['user']->privilege()->effectiveClassLevel(),
            $this->userList['admin']
        );
        $article->setField('Body', 'stuff ' . $article->publicLocation() . ' stuff')->modify();
        $this->assertStringContainsString($article->location(), $article->body(), 'wiki-reg-selfref');
    }
}
