<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TextTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        if (isset($this->userList)) {
            foreach ($this->userList as $user) {
                $user->remove();
            }
        }
    }

    #[DataProvider('dataTeX')]
    public function testTeX(string $name, string $bbcode, string $expected): void {
        $this->assertEquals($expected, \Text::full_format($bbcode), $name);
    }

    public static function dataTeX(): array {
        return [
            ['text-tex-basic',  '[tex]some formula[/tex]',                '<katex>some formula</katex>'],
            ['text-tex-outer',  '[size=4][tex]some formula[/tex][/size]', '<span class="size4"><katex>some formula</katex></span>'],
            ['text-tex-nested', '[tex]some [b]formula[/b][/tex]',         '<katex>some [b]formula[/b]</katex>'],
        ];
    }

    #[DataProvider('dataBB')]
    public function testBB(string $name, string $bbcode, string $expected): void {
        $this->userList['user'] = \GazelleUnitTest\Helper::makeUser('bb.' . randomString(6), 'text');
        \Text::setViewer($this->userList['user']);
        $this->assertEquals($expected, \Text::full_format($bbcode), $name);
    }

    public static function dataBB(): array {
        $url = 'https://www.example.com/';
        return [
            ["text-align-l",    "[align=left]o[/align]",         "<div style=\"text-align: left;\">o</div>"],
            ["text-artist",     "[artist]Frank Zappa[/artist]",  "<a href=\"artist.php?artistname=Frank+Zappa\">Frank Zappa</a>"],
            ["text-b",          "[b]o[/b]",                      "<strong>o</strong>"],
            ["text-br",         "a\nb",                          "a<br />\nb"],
            ["text-box",        "[box]jellyfish[/box]",          "<div class=\"box pad\" style=\"padding: 10px 10px 10px 20px\">jellyfish</div>"],
            ["text-code",       "[code]ab\ncd[/code]",           "<code>ab<br />\ncd</code>"],
            ["text-color-name", "[color=cornsilk]pink[/color]",  "<span style=\"color: cornsilk;\">pink</span>"],
            ["text-color-hash", "[color=#c0ffee]black[/color]",  "<span style=\"color: #c0ffee;\">black</span>"],
            ["text-colour",     "[colour=gold]pink[/colour]",    "<span style=\"color: gold;\">pink</span>"],
            ["text-headline",   "[headline]abc[/headline]",      "=abc="],
            ["text-hr",         "[hr]",                          "<hr />"],
            ["text-i",          "[i]am[/i]",                     "<span style=\"font-style: italic;\">am</span>"],
            ["text-important",  "[important]x[/important]",      "<strong class=\"important_text\">x</strong>"],
            ["text-inlinesize", "[inlinesize=7]b[/inlinesize]",  "<span class=\"size7\">b</span>"],
            ["text-n",          "[[n]i]am[[n]/i]",               "[i]am[/i]"],
            ["text-pad",        "[pad=2|4|6|8]text[/pad]",       "<span style=\"display: inline-block; padding: 2px 4px 6px 8px\">text</span>"],
            ["text-plain",      "[plain]a[b]c[/plain]",          "a[b]c"],
            ["text-pre",        "[pre]a\nb[/pre]",               "<pre>a<br />\nb</pre>"],
            ["text-quote-n",    "[quote]a[/quote]",              "<blockquote>a</blockquote>"],
            ["text-quote-y",    "[quote=phpunit]a[/quote]",      "<strong class=\"quoteheader\">phpunit</strong> wrote: <blockquote>a</blockquote>"],
            ["text-rule",       "[rule]1[/rule]",                "<a href=\"rules.php?p=upload#r1\">1</a>"],
            ["text-s",          "[s]sss[/s]",                    "<span style=\"text-decoration: line-through;\">sss</span>"],
            ["text-size",       "[size=4]big[/size]",            "<span class=\"size4\">big</span>"],
            ["text-s",          "[s]sss[/s]",                    "<span style=\"text-decoration: line-through;\">sss</span>"],
            ["text-u",          "[u]uuu[/u]",                    "<span style=\"text-decoration: underline;\">uuu</span>"],
            ["text-url",        $url,                            "<a rel=\"noreferrer\" target=\"_blank\" href=\"https://www.example.com/\">https://www.example.com/</a>"],

            ["text-hide",       "[hide]secret[/hide]",
                "<strong>Hidden text</strong>: <a href=\"javascript:void(0);\" onclick=\"BBCode.spoiler(this);\">Show</a><blockquote class=\"hidden spoiler\">secret</blockquote>"
            ],
            ["text-spoiler",    "[spoiler]surprise[/spoiler]",
                "<strong>Hidden text</strong>: <a href=\"javascript:void(0);\" onclick=\"BBCode.spoiler(this);\">Show</a><blockquote class=\"hidden spoiler\">surprise</blockquote>"
            ],

            ["text-emoji",      ":nod:",             "<img border=\"0\" src=\"" . STATIC_SERVER . "/common/smileys/nod.gif\" alt=\"\" />"],

            ["text-nest-1",     "[size=3][i]z[/i][/size]", "<span class=\"size3\"><span style=\"font-style: italic;\">z</span></span>"],
            ["text-nest-2",
                "[quote=user]abc [b]xyz[/b] def[/quote] [b][i][s][u]ghi[/u][/s][/i][/b]",
                "<strong class=\"quoteheader\">user</strong> wrote: <blockquote>abc <strong>xyz</strong> def</blockquote> <strong><span style=\"font-style: italic;\"><span style=\"text-decoration: line-through;\"><span style=\"text-decoration: underline;\">ghi</span></span></span></strong>"
            ],
            ["text-nest-3",     "[quote]<I am test>[/quote]", "<blockquote>&lt;I am test&gt;</blockquote>"],

            ["text-escape-1",   "I'm test",          "I&apos;m test"],
            ["text-escape-2",   "I&apos;m test",     "I&amp;apos;m test"],
            ["text-escape-3",   "I&amp;apos;m test", "I&amp;amp;apos;m test"],
            ["text-escape-4",   "<I am test>",       "&lt;I am test&gt;"],
        ];
    }

    public function testImage(): void {
        $bad = "[img=https://www.example.com/a.pdf]";
        $this->assertEquals($bad, $bad, "text-image-bad");

        foreach (['avif', 'bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'tiff', 'webp'] as $ext) {
            $image     = "https://www.example.com/a.jpg";
            $withCache = '@^<img loading="lazy" class="scale_image" onclick=".*?" alt=".*?/i/full/[\w-]+/[\w-]+" src=".*?/i/full/[\w-]+/[\w-]+" data-origin-src="\Q' . $image . '\E" />$@';
            $noCache   = "<img loading=\"lazy\" class=\"scale_image\" onclick=\"lightbox.init(this, \$(this).width());\" alt=\"$image\" src=\"$image\" />";

            $this->assertEquals($noCache, \Text::full_format("[img=$image]"), "text-image1-cache-implicit-$ext");
            $this->assertEquals($noCache, \Text::full_format("[img]{$image}[/img]"), "text-image2-cache-implicit-$ext");
        }
    }

    public function testCollage(): void {
        $this->userList['admin'] = \GazelleUnitTest\Helper::makeUser('collage.' . randomString(6), 'text');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $name    = 'collage ' . randomString(6);
        $collage = (new Manager\Collage())->create($this->userList['admin'], 1, $name, 'phpunit collage', 'jazz,disco');
        $this->assertInstanceOf(Collage::class, $collage, 'text-create-collage');
        $this->assertEquals(
            "<a href=\"collages.php?id={$collage->id()}\">{$collage->name()}</a>",
            \Text::full_format("[collage]{$collage->id()}[/collage]"),
            'text-collage-bb'
        );
        $this->assertEquals(
            "<a href=\"collages.php?id={$collage->id()}\">{$collage->name()}</a>",
            \Text::full_format($collage->publicLocation()),
            'text-collage-url'
        );
        $commentMan = new Manager\Comment();
        $comment    = $commentMan->create($this->userList['admin'], 'collages', $collage->id(), "nice collage!");
        $this->assertEquals(
            "<a href=\"{$comment->url()}\">Collages Comment #{$comment->id()}</a>",
            \Text::full_format($comment->publicLocation()),
            'text-collage-comment-link'
        );
        $this->assertEquals(1, $collage->hardRemove(), 'text-remove-collage');
    }

    public function testForum(): void {
        $this->userList['admin'] = \GazelleUnitTest\Helper::makeUser('forum.' . randomString(6), 'text');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        \Text::setViewer($this->userList['admin']);
        $name  = 'forum ' . randomString(6);
        $category = (new Manager\ForumCategory())->create($name, 10003);
        $forum = \GazelleUnitTest\Helper::makeForum(
            user:        $this->userList['admin'],
            sequence:    999,
            category:    $category,
            name:        $name,
            description: 'phpunit forum test',
        );
        $this->assertInstanceOf(Forum::class, $forum, 'text-create-forum');
        $this->assertEquals(
            "<a href=\"forums.php?action=viewforum&amp;forumid={$forum->id()}\" class=\"tooltip\" title=\"{$forum->name()}\">{$forum->name()}</a>",
            \Text::full_format("[forum]{$forum->id()}[/forum]"),
            'text-forum'
        );

        $thread = (new Manager\ForumThread())->create(
            $forum, $this->userList['admin'], "phpunit thread title", "phpunit thread body"
        );

        $postId = (int)DB::DB()->scalar("
            SELECT min(fp.ID)
            FROM forums_posts fp
            INNER JOIN forums_topics ft ON (ft.ID = fp.TopicID)
        ");
        $post = (new Manager\ForumPost())->findById($postId);
        $threadId = $post->thread()->id();
        $title    = $post->thread()->title();

        $this->assertMatchesRegularExpression(
            "@^<a href=\"forums\.php\?action=viewthread&amp;threadid={$threadId}\">\Q{$title}\E</a>$@",
            \Text::full_format("[thread]{$threadId}[/thread]"),
            'text-forum-thread'
        );

        $this->assertMatchesRegularExpression(
            "@^<a href=\"forums\.php\?action=viewthread&amp;threadid={$threadId}&amp;postid={$postId}#post{$postId}\">\Q{$title}\E \(Post #{$postId}\)</a>$@",
            \Text::full_format("[thread]{$threadId}:{$postId}[/thread]"),
            'text-forum-post'
        );

        $this->assertMatchesRegularExpression(
            "@^<a href=\"forums\.php\?action=viewthread&amp;threadid={$threadId}\">\Q{$title}\E</a>$@",
            \Text::full_format(SITE_URL . "/forums.php?action=viewthread&threadid={$threadId}"),
            'text-forum-thread-link'
        );

        $this->assertMatchesRegularExpression(
            "@^<a href=\"forums\.php\?action=viewthread&amp;threadid={$threadId}&amp;postid={$postId}#post{$postId}\">\Q{$title}\E \(Post #{$postId}\)</a>$@",
            \Text::full_format(SITE_URL . "/forums.php?action=viewthread&threadid={$threadId}&postid={$postId}#post{$postId}"),
            'text-forum-post-link'
        );

        $this->assertEquals(2, $thread->remove(), 'text-remove-thread');
        $this->assertEquals(1, $forum->remove(), 'text-remove-forum');
        $category->remove();
    }

    public static function dataList(): array {
        return [
            ['text-list-a', <<<END_BB
[*] one
[*] two
END_BB, <<<END_HTML
<ul class="postlist"><li>one</li><li>two</li></ul>
END_HTML],
            ['text-list-b', <<<END_BB
intro
[*] one
[*] two
outro
END_BB, <<<END_HTML
intro<br />
<ul class="postlist"><li>one</li><li>two</li></ul><br />
outro
END_HTML],
            ['text-list-c', <<<END_BB
[*] one [*]
[*] two
END_BB, <<<END_HTML
<ul class="postlist"><li>one [*]</li><li>two</li></ul>
END_HTML],
            ['text-list-d', <<<END_BB
[*] one [#]
[*] two
END_BB, <<<END_HTML
<ul class="postlist"><li>one [#]</li><li>two</li></ul>
END_HTML],
            ['text-list-e', <<<END_BB
[*] one
[*] two [*]
END_BB, <<<END_HTML
<ul class="postlist"><li>one</li><li>two [*]</li></ul>
END_HTML],
            ['text-list-f', <<<END_BB
[*] two [*]
outro
END_BB, <<<END_HTML
<ul class="postlist"><li>two [*]</li></ul><br />
outro
END_HTML],
            ['text-list-g', <<<END_BB
[#] alpha
[#] beta
[#] delta
END_BB, <<<END_HTML
<ol class="postlist"><li>alpha</li><li>beta</li><li>delta</li></ol>
END_HTML],
            ['text-list-g', <<<END_BB
[#] alpha
[#] beta
[#] delta
END_BB, <<<END_HTML
<ol class="postlist"><li>alpha</li><li>beta</li><li>delta</li></ol>
END_HTML],
            ['text-list-h', "[*]", <<<END_HTML
<ul class="postlist"><li></li></ul>
END_HTML],
        ];
    }

    #[DataProvider('dataList')]
    public function testList(string $name, string $bbcode, string $expected): void {
        $this->assertEquals($expected, \Text::full_format($bbcode), $name);
    }

    public function testStrip(): void {
        $url = 'https://www.example.com';
        $this->assertEquals(
            'https://www.example.com/a.png https://www.example.com/b.png https://www.example.com here',
            \Text::strip_bbcode("[img]{$url}/a.png[/img] [img={$url}/b.png] [url]{$url}[/url] [url={$url}]here[/url]"),
            'text-strip-bb'
        );
    }

    public function testTOC(): void {
        \Text::$TOC = true;
        $html = \Text::full_format(<<<END_BB
==== BIG ====

abc

=== Big ===

def

=== Also Big ===

ghi

== Smaller ==

jkl

== Small ==

mno
END_BB);
        $expected = <<<END_HTML
<ol class="navigation_list"><li><ol><li>
<ol>
<li><a href="#_3033085760"> BIG </a></li></ol></li><li><a href="#_1538387464"> Big </a></li><li><a href="#_175084667"> Also Big </a></li></ol></li><li><a href="#_3540668408"> Smaller </a></li><li><a href="#_442279556"> Small </a></li></ol>
END_HTML;
        $this->assertEquals($expected, \Text::parse_toc(), 'text-toc-default');

        $expected = <<<END_HTML
<ul><li><ul><li>
<ul>
<li><a href="#_3033085760"> BIG </a></li></ul></li><li><a href="#_1538387464"> Big </a></li><li><a href="#_175084667"> Also Big </a></li></ul></li><li><a href="#_3540668408"> Smaller </a></li><li><a href="#_442279556"> Small </a></li></ul>
END_HTML;
        $this->assertEquals($expected, \Text::parse_toc(3, true), 'text-toc-3-true');
        \Text::$TOC = false;
    }

    public static function dataSpan(): array {
        return [
            ["span-hr",       "a[hr]b",                               "a b"],
            ["span-img",      "[img]https://example.com/a.jpg[/img]", ""],
            ["span-img-2",    "[img=https://example.com/a.jpg]",      ""],
            ["span-pre",      "[pre]a\nb[/pre]",                      "a b"],
            ["span-quote",    "[quote]abcd[/quote]",                  "abcd"],
            ["span-quote-in", "[quote=who]abcd[/quote]",              "abcd"],
            ["span-raw-url",  "https://example.com",                  "https://example.com"],
            ["span-url",      "[url=https://example.com/]",           "https://example.com/"],
            ["span-url-2",    "x[url=https://example.com/a.jpg]y",    "xy"],
        ];
    }

    #[DataProvider('dataSpan')]
    public function testSpanText(string $name, string $bbcode, string $expected): void {
        $this->assertEquals($expected, \Text::span_format($bbcode), $name);
    }

    public function testUser(): void {
        $username = 'text.' . randomString(6);
        $this->userList['user'] = \GazelleUnitTest\Helper::makeUser($username, 'text');
        \Text::setViewer($this->userList['user']);

        $this->assertEquals("<a href=\"user.php?action=search&amp;search=$username\">$username</a>", \Text::full_format("[user]{$username}[/user]"), "text-user-1");

        $url = "<a href=\"user.php?id={$this->userList['user']->id()}\">@$username</a>";
        $this->assertEquals($url, \Text::full_format("@$username"), "text-user-2");
        $this->assertEquals("$url.", \Text::full_format("@$username."), "text-user-3");

        $this->assertEquals(
            "<span class=\"mature_blocked\" style=\"font-style: italic;\"><a href=\"wiki.php?action=article&amp;id=1063\">Mature content</a> has been blocked. You can choose to view mature content by editing your <a href=\"user.php?action=edit&amp;id=me\">settings</a>.</span>",
            \Text::full_format("[mature]titties[/mature]"),
            "text-mature",
        );
    }
}
