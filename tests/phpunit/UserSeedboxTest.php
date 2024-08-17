<?php

use PHPUnit\Framework\TestCase;

class UserSeedboxTest extends TestCase {
    protected Gazelle\User $user;
    protected string $tgroupName;
    protected array $torrentList = [];

    public function setUp(): void {
        $this->user = Helper::makeUser('sbox.' . randomString(10), 'seedbox');
        $this->user->toggleAttr('feature-seedbox', true);

        $this->tgroupName = 'phpunit seedbox ' . randomString(6);
        $tgroup = Helper::makeTGroupMusic(
            name:       $this->tgroupName,
            artistName: [[ARTIST_MAIN], ['Seed Box ' . randomString(12)]],
            tagName:    ['metal'],
            user:       $this->user,
        );

        $this->torrentList = array_map(fn($info) =>
            Helper::makeTorrentMusic(
                tgroup: $tgroup,
                user:   $this->user,
                title:  $info['title'],
            ), [
                ['title' => 'Standard Edition'],
                ['title' => 'Deluxe Edition'],
                ['title' => 'Limited Edition'],
                ['title' => 'Remix Edition'],
            ]
        );
    }

    public function tearDown(): void {
        Helper::removeTGroup($this->torrentList[0]->group(), $this->user);
        Gazelle\DB::DB()->prepared_query("
            DELETE us, xfu
            FROM user_seedbox us
            LEFT JOIN xbt_files_users xfu ON (xfu.uid = us.user_id)
            WHERE us.user_id = ?
            ", $this->user->id()
        );
        $this->user->remove();
    }

    protected function generateSeed(Gazelle\Torrent $torrent, string $ua, string $peerId, string $ip): void {
        Gazelle\DB::DB()->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, ip, active, remaining, timespent, mtime)
            VALUES (?,   ?,   ?,         ?,       ?,  1, 0, 1, unix_timestamp(now() - interval 1 hour))
            ",  $torrent->id(), $this->user->id(), $ua, $peerId, $ip
        );
    }

    public function testSeedbox(): void {
        $seedbox = new Gazelle\User\Seedbox($this->user);
        $this->assertEquals($this->user->link(), $seedbox->link(), 'seedbox-link');
        $this->assertEquals($this->user->location(), $seedbox->location(), 'seedbox-location');
        $this->assertCount(0, $seedbox->hostList(), 'seedbox-initial-hostlist');
        $this->assertCount(0, $seedbox->freeList(), 'seedbox-initial-freelist');
        $seedbox->setViewByPath();
        $this->assertEquals(Gazelle\User\Seedbox::VIEW_BY_PATH, $seedbox->viewBy(), 'seedbox-view-by');

        $ua1   = 'ua-' . randomString(10);
        $pid1  = 'pi-' . randomString(10);
        $ip1   = '172.16.0.1';
        $key1  = "$ip1/$ua1";
        $name1 = "$ip1::$ua1";
        $this->generateSeed($this->torrentList[0], $ua1, $pid1, $ip1);
        $this->generateSeed($this->torrentList[1], $ua1, $pid1, $ip1);

        $ua2   = $ua1 . '2';
        $pid2  = $pid1 . '2';
        $ip2   = '172.20.0.1';
        $key2  = "$ip2/$ua2";
        $name2 = "$ip2::$ua2";
        $this->generateSeed($this->torrentList[1], $ua2, $pid2, $ip2);
        $this->generateSeed($this->torrentList[2], $ua2, $pid2, $ip2);
        $this->generateSeed($this->torrentList[3], $ua2, $pid2, $ip2);

        $seedbox->flush();
        $hostList = $seedbox->hostList();
        $this->assertEquals([$key1, $key2], array_keys($hostList), 'seedbox-hostlist');
        $this->assertEquals(2, $hostList[$key1]['total'], 'seedbox-total-1');
        $this->assertEquals(3, $hostList[$key2]['total'], 'seedbox-total-2');
        $this->assertEquals($name1, $hostList[$key1]['name'], 'seedbox-name-1');
        $this->assertEquals($name2, $hostList[$key2]['name'], 'seedbox-name-2');

        $changed = $seedbox->updateNames([
            [
                'name' => 'sbox-1',
                'id'   => $hostList[$key1]['id'],
                'ipv4' => $hostList[$key1]['ipv4addr'],
                'ua'   => $hostList[$key1]['useragent'],
            ],[
                'name' => 'sbox-2',
                'id'   => $hostList[$key2]['id'],
                'ipv4' => $hostList[$key2]['ipv4addr'],
                'ua'   => $hostList[$key2]['useragent'],
            ]
        ]);
        $this->assertEquals(2, $changed, 'seedbox-renamed');
        $hostList = $seedbox->hostList();
        $this->assertEquals('sbox-1', $hostList[$key1]['name'], 'seedbox-rename-1');
        $this->assertEquals('sbox-2', $hostList[$key2]['name'], 'seedbox-rename-2');
        $this->assertEquals('sbox-1', $seedbox->name($hostList[$key1]['id']), 'seedbox-id-1');
        $this->assertEquals('sbox-2', $seedbox->name($hostList[$key2]['id']), 'seedbox-id-2');

        // union (torrentList[1] in common)
        $seedbox->setSource($hostList[$key1]['id'])->setTarget($hostList[$key2]['id'])->setUnion(true);
        $this->assertEquals([$this->torrentList[1]->id()], $seedbox->idList(), 'seedbox-both');

        // intersection (point of view sbox-1)
        $seedbox->setUnion(false);
        $this->assertEquals([$this->torrentList[0]->id()], $seedbox->idList(), 'seedbox-1-not-2');

        // intersection (point of view sbox-2)
        $seedbox->setSource($hostList[$key2]['id'])->setTarget($hostList[$key1]['id']);
        $this->assertEquals([$this->torrentList[2]->id(), $this->torrentList[3]->id()], $seedbox->idList(), 'seedbox-2-not-1');

        $list = $seedbox->torrentList(new Gazelle\Manager\Torrent(), 3, 0);
        $this->assertEquals([$this->torrentList[2]->id(), $this->torrentList[3]->id()], array_map(fn($t) => $t['id'], $list), 'seedbox-list');
    }

    public function testUserSeederList(): void {
        $torrent = $this->torrentList[0];
        Helper::generateTorrentSeed($torrent, $this->user);
        $seederList = $torrent->seederList($this->user, 1, 0);
        $this->assertCount(1, $seederList, 'seedbox-user-seederlist');
    }
}
