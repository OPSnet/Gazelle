<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class UserJsonTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('user.' . randomString(6), 'json'),
            'user'  => Helper::makeUser('user.' . randomString(6), 'json'),
        ];
        $this->userList['admin']
            ->setField('PermissionID', SYSOP)
            ->setField('Paranoia', serialize(['uploaded', 'uploads']))
            ->modify();
        $this->userList['user']
            ->setField('Paranoia', serialize(['uploaded']))
            ->modify();
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            Helper::removeUser($user);
        }
    }

    public function testJsonUserAdmin(): void {
        $json = new Json\User($this->userList['admin'], $this->userList['user']);
        $payload = $json->payload();
        $this->assertEquals(
            $this->userList['admin']->username(),
            $payload['username'],
            'json-user-admin-username'
        );
        $this->assertEquals(
            [
                "username",
                "avatar",
                "isFriend",
                "profileText",
                "stats",
                "ranks",
                "personal",
                "community",
            ],
            array_keys($payload),
            'json-user-payload-keys',
        );
        $this->assertNull($payload['personal']['passkey'], 'json-user-admin-passkey-private');
        $this->assertNull($payload['stats']['uploaded'], 'json-user-admin-uploaded-paranoia');
    }

    public function testJsonUserUser(): void {
        $json = new Json\User($this->userList['user'], $this->userList['admin']);
        $payload = $json->payload();
        $this->assertEquals(
            $this->userList['user']->username(),
            $payload['username'],
            'json-user-user-username'
        );
        $this->assertEquals(
            $this->userList['user']->announceKey(),
            $payload['personal']['passkey'],
            'json-user-user-passkey-visible'
        );
        $this->assertEquals(
            STARTING_UPLOAD,
            $payload['stats']['uploaded'],
            'json-user-user-uploaded-override'
        );

        $this->userList['admin']->requestContext()->setViewer($this->userList['admin']);
        $torrent = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                $this->userList['admin'],
                randomString(10),
                [[ARTIST_MAIN], ['phpunit json user ' . randomString(10)]],
                ['funk'],
            ),
            $this->userList['admin'],
        );
        $recent = new Json\UserRecent(
            $this->userList['admin'],
            $this->userList['admin'],
        );
        $payload = $recent->payload();
        $this->assertEquals([], $payload['snatches'], 'json-user-recent-admin-snatch');
        $this->assertEquals(
            $torrent->groupId(),
            $payload['uploads'][0]['ID'],
            'json-user-recent-admin-upload'
        );

        $this->assertEquals(
            'hidden',
            new Json\UserRecent(
                $this->userList['admin'],
                $this->userList['user'],
            )->payload()['uploads'],
            'json-user-recent-user-upload'
        );
    }

    public function testJsonUserSearch(): void {
        $json = new Json\UserSearch(
            substr($this->userList['admin']->username(), 0, -1),
            $this->userList['admin'],
            new Util\Paginator(10, 1),
        );
        $payload = $json->payload();

        $this->assertEquals(1, $payload['pages'], 'json-user-search-page-total');
        $this->assertEquals(1, $payload['currentPage'], 'json-user-search-page-current');
        $this->assertCount(1, $payload['results'], 'json-user-search-results');
        $result = current($payload['results']);
        $this->assertEquals(
            $this->userList['admin']->id,
            $result['userId'],
            'json-user-search-id',
        );
        $this->assertEquals(
            $this->userList['admin']->username(),
            $result['username'],
            'json-user-search-username',
        );

        $this->userList['user']->modifyPrivilegeList([
            'site_advanced_search' => false,
        ]);
        $json = new Json\UserSearch(
            substr($this->userList['admin']->username(), 0, -1),
            $this->userList['user'],
            new Util\Paginator(10, 1),
        );
        $payload = $json->payload();
        $this->assertEquals(0, $payload['pages'], 'json-user-search-simple-page-total');
        $this->assertEquals([], $payload['results'], 'json-user-search-simple-results');
    }
}
