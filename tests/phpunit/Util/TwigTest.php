<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class TwigTest extends TestCase {
    public function setUp(): void {
        Gazelle\Util\Twig::setUserMan(new Gazelle\Manager\User);
    }

    protected static function twig(string $template) {
        return Gazelle\Util\Twig::factory()->createTemplate($template);
    }

    public function testDominator() {
        $twig = Gazelle\Util\Twig::factory();
        $twig->createTemplate("{{ dom.click('#id', \"$('#id').frob(); return false;\") }}")->render();
        $expected = <<<END
<script type="text/javascript">document.addEventListener('DOMContentLoaded', function() {
\$('#id').click(function () {\$('#id').frob(); return false;});
})</script>
END;
        $this->assertEquals($expected, $twig->createTemplate('{{ dom.emit|raw }}')->render(), 'twig-dominator');
    }

    public function testFilter() {
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

        $checked = self::twig('
            {%- from "macro/form.twig" import checked -%}
            <input type="checkbox" name="test"{{ truth|checked }} />');
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

        $this->assertEquals('abc "def"…', self::twig('{{ value|shorten(10) }}')->render(['value' => 'abc "def" ghi']), 'twig-shorten-10');

        $this->assertMatchesRegularExpression(
            '@^<span class="time tooltip" title="[^"]+">1 hour ago</span>$@',
            self::twig('{{ value|time_diff }}')->render(['value' => date("Y-m-d H:i:s", strtotime("-1 hour"))]),
            'twig-time-diff'
        );

        $this->assertEquals(
            '2h7s',
            self::twig('{{ value|time_interval }}')->render(['value' => 3600 * 2 + 7]),
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
        $Viewer = (new Gazelle\Manager\User)->find('@admin');
        $this->assertEquals('<a href="user.php?id=1">admin</a>', self::twig('{{ user_id|user_url }}')->render(['user_id' => 1]), 'twig-user-url');
        $this->assertMatchesRegularExpression(
            '@^<a href="user.php\?id=1">admin</a><a target="_blank" href="[^"]+"><img class="donor_icon tooltip" src="[^"]+" (?:alt="[^"]+" )?(?:title="[^"]+" )?/></a> \(Sysop\)$@',
            self::twig('{{ user_id|user_full }}')->render(['user_id' => 1]),
            'twig-user-full'
        );

        $status = self::twig('{{ user_id|user_status(viewer) }}');
        $this->assertIsString($status->render(['user_id' => 1, 'viewer' => $Viewer]), 'twig-user-status');
    }

    public function testFunction() {
        global $Document;
        $Document = 'index';
        $this->assertStringStartsWith('<!DOCTYPE html>', self::twig('{{ header("page") }}')->render(), 'twig-function-header');

        global $Viewer;
        $Viewer  = (new Gazelle\Manager\User)->find('@admin');
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

    public function testTest() {
        $admin = (new Gazelle\Manager\User)->find('@admin');
        $this->assertEquals('yes', self::twig('{% if user is donor %}yes{% endif %}')->render(['user' => $admin]), 'twig-test-donor');

        $this->assertEquals('yes', self::twig('{% if value is nan %}yes{% endif %}')->render(['value' => sqrt(-1)]), 'twig-test-nan');

        $this->assertEquals(
            'yes',
            self::twig('{% if value is request_fill %}yes{% endif %}')->render(['value' => new Gazelle\Contest\RequestFill(0, '', '')]),
            'twig-test-contest-request-fill'
        );
    }
}
