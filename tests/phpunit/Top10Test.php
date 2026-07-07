<?php

namespace Gazelle;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class Top10Test extends TestCase {
    protected User $user;
    protected static array $userFieldList = [
        "id", "created", "uploaded", "downloaded", "request_votes", "request_fills", "num_uploads", "up_speed", "down_speed"
    ];

    public function setUp(): void {
        $this->user = Helper::makeUser('topten.' . randomString(6), 'topten', enable: true);
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testTop10TorrentBasic(): void {
        $top10 = new Top10\Torrent(FORMAT, $this->user);
        global $Cache;

        $key = "T10_all_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([]),
            'top10-t-basic'
        );
        $this->assertEquals(
            [],
            $top10->getTopTorrents([]),
            'top10-t-cached-basic'
        );

        $key = "T10_all_10_" . trim(signature('FLAC', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents(['format' => 'FLAC']),
            'top10-t-format'
        );
        $key = "T10_all_10_" . trim(signature('FAKE', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents(['format' => 'FAKE']),
            'top10-t-fake-format'
        );

        $key = "T10_all_10_" . trim(signature('hide', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents(['freeleech' => 'hide']),
            'top10-t-freeleech'
        );

        $key = "T10_all_10_" . trim(signature('phpunit.a,phpunit.b', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([
                'tags' => 'phpunit.a,phpunit.b',
            ]),
            'top10-t-tag-any'
        );
        $key = "T10_all_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([
                'tags' => '',
            ]),
            'top10-t-tag-none'
        );

        $key = "T10_all_10_" . trim(signature('phpunit.a,phpunit.b|all', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([
                'tags'   => 'phpunit.a,phpunit.b',
                'anyall' => 'all',
            ]),
            'top10-t-tag-all'
        );

        $key = "T10_all_10_" . trim(signature('xcludedartiste', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents(['excluded_artists' => 'xcludedartiste']),
            'top10-t-artist'
        );
        $key = "T10_all_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents(['excluded_artists' => '']),
            'top10-t-no-artist'
        );
    }

    public function testTop10TorrentOrder(): void {
        $top10 = new Top10\Torrent(FORMAT, $this->user);
        global $Cache;

        $key = "T10_snatched_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'snatched'),
            'top10-t-snatched'
        );

        $key = "T10_seeded_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'seeded'),
            'top10-t-seeded'
        );

        $key = "T10_data_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'data'),
            'top10-t-data'
        );
    }

    public function testTop10TorrentInterval(): void {
        $top10 = new Top10\Torrent(FORMAT, $this->user);
        global $Cache;

        $key = "T10_day_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'day'),
            'top10-t-day'
        );

        $key = "T10_week_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'week'),
            'top10-t-week'
        );

        $key = "T10_month_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'month'),
            'top10-t-month'
        );

        $key = "T10_year_10_" . trim(signature('', TOP10_SALT), '=');
        $Cache->delete_multi([$key, "{$key}_lock"]);
        $this->assertEquals(
            [],
            $top10->getTopTorrents([], 'year'),
            'top10-t-year'
        );
    }

    public function testTop10UserUploader(): void {
        global $Cache;
        $Cache->delete_value(
            sprintf(Top10\User::CACHE_KEY, Enum\UserTop10Key::uploader->value, 1)
        );
        $db = DB::DB();
        $db->prepared_query("
            UPDATE users_leech_stats SET Uploaded = ? WHERE UserID = ?
            ", 10 * 1024 ** 4, // 10TiB
                $this->user->id
        );

        $uploader = array_first(
            new Top10\User()->fetch(Enum\UserTop10Key::uploader, 1)
        );
        $this->assertEqualsCanonicalizing(
            self::$userFieldList,
            array_keys($uploader),
            'top10-u-uploader-keys',
        );
        $this->assertEquals($this->user->id, $uploader['id'], 'top10-u-uploader');
    }

    public static function dataUserTop10(): array {
        return [
            [Enum\UserTop10Key::downloader],
            [Enum\UserTop10Key::uploadTotal],
            [Enum\UserTop10Key::requestVote],
            [Enum\UserTop10Key::requestFill],
            [Enum\UserTop10Key::uploadSpeed],
            [Enum\UserTop10Key::downloadSpeed],
        ];
    }

    #[DataProvider('dataUserTop10')]
    public function testTop10UserVariant(Enum\UserTop10Key $type): void {
        global $Cache;
        $Cache->delete_value(
            sprintf(Top10\User::CACHE_KEY, $type->value, 1)
        );
        $this->assertEqualsCanonicalizing(
            self::$userFieldList,
            array_keys(array_first(new Top10\User()->fetch($type, 1))),
            "top10-u-{$type->value}-keys",
        );
    }
}
