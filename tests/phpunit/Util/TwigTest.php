<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

use Gazelle\Enum\AvatarDisplay;
use Gazelle\Enum\AvatarSynthetic;

class TwigTest extends TestCase {
    protected \Gazelle\User $user;

    public function setUp(): void {
        Gazelle\Util\Twig::setUserMan(new Gazelle\Manager\User());
        $this->user = Helper::makeUser('user.' . randomString(6), 'user');
        $this->user->setField('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    protected static function twig(string $template): \Twig\TemplateWrapper {
        return Gazelle\Util\Twig::factory()->createTemplate($template);
    }

    public function testDominator(): void {
        (new \Gazelle\Util\Dominator())->flush(); // in case anything has already been set
        $twig = Gazelle\Util\Twig::factory();
        $twig->createTemplate("{{ dom.click('#id', \"$('#id').frob(); return false;\") }}")->render();
        $expected = <<<END
<script type="text/javascript">document.addEventListener('DOMContentLoaded', function() {
\$('#id').click(function () {\$('#id').frob(); return false;});
})</script>
END;
        $this->assertEquals($expected, $twig->createTemplate('{{ dom.emit|raw }}')->render(), 'twig-dominator');
    }

    public function testFilter(): void {
        $article = self::twig('{{ word|article }}');
        $this->assertEquals('a', $article->render(['word' => 'consonant']), 'twig-article-a');
        $this->assertEquals('an', $article->render(['word' => 'anticonsonant']), 'twig-article-an');

        $this->assertEquals('YmxvYg==', self::twig('{{ b|b64 }}')->render(['b' => 'blob']), 'twig-b64');

        // badge_list is weird

        $this->assertEquals(
            '<strong>bold</strong>',
            self::twig('{{ text|bb_format }}')->render(['text' => '[b]bold[/b]']),
            'twig-bb-format'
        );

        $this->assertStringStartsWith(
            '<img loading="lazy" class="scale_image" onclick="lightbox.init(this, $(this).width());" alt="' . IMAGE_CACHE_HOST . '/f/full/',
            self::twig('{{ text|bb_forum }}')->render(['text' => '[img=https://example.com/image.jpg]']),
            'twig-bb-forum'
        );

        $checked = self::twig('{%- from "macro/form.twig" import checked -%}<input type="checkbox" name="test"{{ truth|checked }} />');
        $this->assertEquals(
            '<input type="checkbox" name="test" checked="checked" />',
            $checked->render(['truth' => true]),
            'twig-checked-true'
        );
        $this->assertEquals(
            '<input type="checkbox" name="test" />',
            $checked->render(['truth' => false]),
            'twig-checked-false'
        );

        $sth = new \Gazelle\Util\SortableTableHeader('alpha', [
            'alpha' => ['dbColumn' => 'one', 'defaultSort' => 'desc', 'text' => 'First'],
            'beta'  => ['dbColumn' => 'two', 'defaultSort' => 'desc', 'text' => 'Second'],
        ]);
        $heading = self::twig('{{ heading|column("alpha") }}');
        $this->assertEquals('<a href="?order=alpha&amp;sort=asc">First</a> &uarr;', $heading->render(['heading' => $sth]), 'twig-heading');

        $linkify = self::twig('{{ url|linkify }}');
        $this->assertEquals(
            '<a href="/index.php">/index.php</a>',
            $linkify->render(['url' => '/index.php']),
            'twig-linkify-index'
        );
        $this->assertEquals(
            '<a href="' . SITE_URL . '/index.php">' . SITE_URL . '/index.php</a>',
            $linkify->render(['url' => SITE_URL . '/index.php']),
            'twig-linkify-site-index'
        );

        $this->assertEquals('3.00 MiB', self::twig('{{ size|octet_size }}')->render(['size' => 1024 * 1024 * 3]), 'twig-octet-3.00MiB');
        $this->assertEquals('3 MiB',    self::twig('{{ size|octet_size(0) }}')->render(['size' => 1024 * 1024 * 3]), 'twig-octet-3MiB');
        $this->assertEquals('3.675 GiB', self::twig('{{ size|octet_size(3) }}')->render(['size' => 1024 * 1024 * 1024 * 3.675]), 'twig-octet-3.675GiB');
        $this->assertEquals('3.68 GiB',  self::twig('{{ size|octet_size(2) }}')->render(['size' => 1024 * 1024 * 1024 * 3.675]), 'twig-octet-3.68GiB');
        $this->assertEquals('3.7 GiB',   self::twig('{{ size|octet_size(1) }}')->render(['size' => 1024 * 1024 * 1024 * 3.675]), 'twig-octet-3.7GiB');
        $this->assertEquals('4 GiB',     self::twig('{{ size|octet_size(0) }}')->render(['size' => 1024 * 1024 * 1024 * 3.675]), 'twig-octet-4GiB');

        $this->assertEquals('',   self::twig('{{ number|plural }}')->render(['number' => 1]), 'twig-plural-1');
        $this->assertEquals('s',  self::twig('{{ number|plural }}')->render(['number' => 2]), 'twig-plural-2');
        $this->assertEquals('es', self::twig('{{ number|plural("es") }}')->render(['number' => 3]), 'twig-plural-3');

        $this->assertEquals('',           self::twig('{{ value|repeat(0) }}')->render(['value' => 'go']), 'twig-repeat-0');
        $this->assertEquals('gogo',       self::twig('{{ value|repeat(2) }}')->render(['value' => 'go']), 'twig-repeat-2');
        $this->assertEquals('gogogogogo', self::twig('{{ value|repeat(5) }}')->render(['value' => 'go']), 'twig-repeat-5');
        $this->assertEquals('gogogo',     self::twig('{{ value|repeat(n) }}')->render(['value' => 'go', 'n' => 3]), 'twig-repeat-3');

        $this->assertEquals('abc &quot;def&quot;…', self::twig('{{ value|shorten(10) }}')->render(['value' => 'abc "def" ghi']), 'twig-shorten-10');

        $this->assertEquals(
            '2 hours and 7 mins',
            self::twig('{{ value|time_interval }}')->render(['value' => 3600 * 2 + 7 * 60]),
            'twig-time-ago'
        );

        $this->assertEquals(
            '30 mins',
            self::twig('{{ value|time_interval }}')->render(['value' => 1800.125]),
            'twig-time-float-ago'
        );

        $this->assertMatchesRegularExpression(
            '@^<span class="time tooltip" title="[^"]+">1 hour ago</span>$@',
            self::twig('{{ value|time_diff }}')->render(['value' => date("Y-m-d H:i:s", strtotime("-1 hour"))]),
            'twig-time-diff'
        );

        $this->assertEquals(
            '2h7s',
            self::twig('{{ value|time_compact }}')->render(['value' => 3600 * 2 + 7]),
            'twig-time-interval'
        );

        $this->assertEquals(
            '2',
            self::twig('{{ value|token_count }}')->render(['value' => BYTES_PER_FREELEECH_TOKEN * 2 - 1]),
            'twig-token-count-2'
        );

        $this->assertEquals(
            '3',
            self::twig('{{ value|token_count }}')->render(['value' => BYTES_PER_FREELEECH_TOKEN * 2 + 1]),
            'twig-token-count-3'
        );

        $this->assertEquals('✅', self::twig('{{ value|truth }}')->render(['value' => true]), 'twig-truth-true');
        $this->assertEquals('❌', self::twig('{{ value|truth }}')->render(['value' => false]), 'twig-truth-false');

        $this->assertEquals('First time', self::twig('{{ value|ucfirst }}')->render(['value' => 'first time']), 'twig-ucfirst-1');
        $this->assertEquals('A Day In The Life', self::twig('{{ value|ucfirstall }}')->render(['value' => 'a day in the life']), 'twig-ucfirstall');

        // yuck
        global $Viewer;
        $Viewer = $this->user;
        $this->assertEquals(
            "<a href=\"user.php?id={$this->user->id()}\">{$this->user->username()}</a>",
            self::twig('{{ user_id|user_url }}')->render(['user_id' => $this->user->id()]),
            'twig-user-url'
        );
        (new Gazelle\User\Donor($this->user))->donate(
            amount:  20,
            xbtRate: 0.05,
            source: 'phpunit twig source',
            reason: 'phpunit twig reason',
        );
        $this->assertMatchesRegularExpression(
            "@^<a href=\"user.php\?id={$this->user->id()}\">{$this->user->username()}</a><a target=\"_blank\" href=\"[^\"]+\"><img class=\"donor_icon tooltip\" src=\"[^\"]+\" (?:alt=\"[^\"]+\" )?(?:title=\"[^\"]+\" )?/></a> \(Sysop\)$@",
            self::twig('{{ user_id|user_full }}')->render(['user_id' => $this->user->id()]),
            'twig-user-full'
        );

        $status = self::twig('{{ user_id|user_status(viewer) }}');
        $this->assertIsString($status->render(['user_id' => $this->user->id(), 'viewer' => $this->user]), 'twig-user-status');
    }

    public function testImageCache(): void {
        $imageCache = self::twig('{{ image|image_cache }}');
        $this->assertStringStartsWith(
            IMAGE_CACHE_HOST . '/i/full/',
            $imageCache->render(['image' => 'https://example.com/image.url']),
            'twig-image-full-enabled'
        );

        $imageCache = self::twig('{{ image|image_cache(height, width) }}');
        $this->assertStringStartsWith(
            IMAGE_CACHE_HOST . '/i/64x96/',
            $imageCache->render(['image' => 'https://example.com/image.url', 'height' => 64, 'width' => 96]),
            'twig-image-64x96'
        );
        $this->assertStringStartsWith(
            IMAGE_CACHE_HOST . '/i/256x/',
            $imageCache->render(['image' => 'https://example.com/image.url', 'height' => 256]),
            'twig-image-height-256'
        );
        $this->assertStringStartsWith(
            IMAGE_CACHE_HOST . '/i/x480/',
            $imageCache->render(['image' => 'https://example.com/image.url', 'width' => 480]),
            'twig-image-width-480'
        );
    }

    public function testImageProxy(): void {
        $imageCache = self::twig('{{ image|image_proxy(active) }}');
        $image = $imageCache->render(['image' => 'https://example.com/image.url', 'active' => true]);
        $this->assertStringStartsWith(IMAGE_CACHE_HOST . '/i/full/', $image, 'twig-image-proxy-spec');
        $this->assertStringEndsWith('/proxy', $image, 'twig-image-proxy-end');

        $image = $imageCache->render(['image' => 'https://example.com/image.url', 'active' => false]);
        $this->assertStringEndsNotWith('/proxy', $image, 'twig-image-no-proxy-end');
    }

    public function testFunction(): void {
        global $Document;
        $Document = 'index';
        global $Viewer;
        $Viewer  = $this->user;
        $this->assertStringStartsWith('<!DOCTYPE html>', self::twig('{{ header("page") }}')->render(), 'twig-function-header');

        $current = (new Gazelle\User\Session($Viewer))->create([
            'keep-logged' => '0',
            'browser'     => [
               'Browser'                => 'phpunit',
               'BrowserVersion'         => '1.0',
               'OperatingSystem'        => 'phpunit/OS',
               'OperatingSystemVersion' => '1.0',
            ],
            'ipaddr'      => '127.0.0.1',
            'useragent'   => 'phpunit',
        ]);
        global $SessionID;
        $SessionID = $current['SessionID'];
        $this->assertStringStartsWith('<!DOCTYPE html>', self::twig('{{ header("page") }}')->render(), 'twig-function-header');
        $this->assertStringEndsWith("</body>\n</html>\n", self::twig('{{ footer() }}')->render(), 'twig-function-footer');

        $this->assertEquals(
            '127.0.0.1 <a href="user.php?action=search&amp;ip_history=on&amp;matchtype=strict&amp;ip=127.0.0.1" title="Search" class="brackets tooltip">S</a>',
            self::twig('{{ ipaddr(ip) }}')->render(['ip' => '127.0.0.1']),
            'twig-function-ipaddr'
        );

        $this->assertIsString(self::twig('{{ mtime(asset) }}')->render(['asset' => 'blank.gif']), 'twig-function-mtime-file');
        $this->assertIsString(self::twig('{{ mtime_scss(asset) }}')->render(['asset' => 'global.scss']), 'twig-function-mtime-scss');

        $this->assertEquals(
            '<span class="tooltip r20" title="2.00000">2.00</span>',
            self::twig('{{ ratio(up, down) }}')->render(['up' => 100, 'down' => 50]),
            'twig-function-ratio-2'
        );
        $this->assertEquals(
            '<span class="tooltip r10" title="1.00000">1.00</span>',
            self::twig('{{ ratio(up, down) }}')->render(['up' => 100, 'down' => 100]),
            'twig-function-ratio-1'
        );
        $this->assertEquals(
            '<span class="tooltip r05" title="0.50000">0.50</span>',
            self::twig('{{ ratio(up, down) }}')->render(['up' => 50, 'down' => 100]),
            'twig-function-ratio-0.5'
        );
        $this->assertEquals(
            '<span class="tooltip r00" title="0.00000">0.00</span>',
            self::twig('{{ ratio(up, down) }}')->render(['up' => 0, 'down' => 100]),
            'twig-function-ratio-0'
        );
        $this->assertEquals(
            '<span class="tooltip r99" title="Infinite">∞</span>',
            self::twig('{{ ratio(up, down) }}')->render(['up' => 50, 'down' => 0]),
            'twig-function-ratio-infin'
        );
    }

    public function testTest(): void {
        $this->assertEquals('yes', self::twig('{% if value is nan %}yes{% endif %}')->render(['value' => sqrt(-1)]), 'twig-test-nan');

        $this->assertEquals(
            'yes',
            self::twig('{% if value is request_fill %}yes{% endif %}')->render(['value' => new Gazelle\Contest\RequestFill(0, '', '')]),
            'twig-test-contest-request-fill'
        );
    }
}
