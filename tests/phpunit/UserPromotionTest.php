<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\AvatarDisplay;
use Gazelle\Enum\AvatarSynthetic;
use Gazelle\Enum\UserStatus;

class UserPromotionTest extends TestCase {
    protected Request $request;
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('promo.' . randomString(6), 'promo', enable: true, clearInbox: true);
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            $this->request->remove();
        }
        $this->user->remove();
    }

    public function testUploadPromotion(): void {
        // if this fails locally, run again
        $userMan = new Manager\User();
        $this->assertEquals(0, $userMan->promote(), 'promote-initial');
        $this->assertEquals(0, $userMan->demote(), 'demote-initial');

        // backdate user creation
        DB::DB()->prepared_query("
            UPDATE users_main SET
                created = created - INTERVAL 8 DAY
            WHERE ID = ?
            ", $this->user->id
        );
        // 11GiB to be eligible for User => Member promotion
        $this->user->setField('leech_upload', 11 * 1024 ** 3)->modify();
        $this->assertEquals(1, $userMan->promote(), 'promote-member-promotion');

        $inbox = $this->user->inbox()->setUnreadFirst(true);
        $this->assertEquals(1, $inbox->messageTotal(), 'promote-inbox-total');
        $list = $inbox->messageList(new Manager\PM($this->user), 1, 0);
        $this->assertEquals('You have been promoted to Member', $list[0]->subject(), 'promote-pm-subject');

        $this->user->setField('leech_upload', 9 * 1024 ** 3)->modify();
        $this->assertEquals(1, $userMan->demote(), 'promote-member-demotion');
        $this->assertEquals(2, $inbox->messageTotal(), 'promote-inbox-total');
        $list = $inbox->messageList(new Manager\PM($this->user), 2, 0);
        $this->assertEquals('You have been demoted to User', $list[0]->subject(), 'demote-pm-subject');
        $this->assertStringContainsString(
            '❌ 10.00 GiB uploaded, you have 9.00 GiB',
            $list[0]->postList(1, 0)[0]['body'],
            'demote-user-fail',
        );
    }

    public function testBountyPromotion(): void {
        $userMan = new Manager\User();
        // backdate user creation
        DB::DB()->prepared_query("
            UPDATE users_main SET
                created = created - INTERVAL 8 DAY
            WHERE ID = ?
            ", $this->user->id
        );
        // 11GiB and create a request for 5GiB to be eligible for User => Member promotion
        $this->user->setField('leech_upload', 11 * 1024 ** 3)->modify();
        $this->request = Helper::makeRequestMusic($this->user, 'phpunit user promote request');
        $this->assertTrue(
            // vote for 5GiB (subtract 100MiB that makeRequestMusic() adds implicitly)
            $this->request->vote($this->user, 5 * 1024 ** 3 - 100 * 1024 * 1024,),
            'promote-member-req-vote',
        );

        // recompute user request stats
        $stats = new Stats\Users();
        $stats->refresh();
        $this->assertEquals(
            1,
            $userMan->promote(),
            'promote-member-req-promotion',
        );

        $inbox = $this->user->inbox();
        $this->assertEquals(1, $inbox->messageTotal(), 'promote-req-inbox-total');
        $list = $inbox->messageList(new Manager\PM($this->user), 2, 0);
        $this->assertEquals(
            'You have been promoted to Member',
            $list[0]->subject(),
            'promote-req-pm-subject',
        );

        $this->request->remove();
        $stats->refresh(); // recompute after removal
        $this->user->flush();   // resync user's cache with reality

        $this->assertEquals(
            1,
            $userMan->demote(),
            'promote-member-req-demotion',
        );
        $list = $inbox->messageList(new Manager\PM($this->user), 2, 0);
        $this->assertEquals(
            'You have been demoted to User',
            $list[0]->subject(),
            'demote-req-pm-subject',
        );
        $this->assertStringContainsString(
            '❌ 10.00 GiB uploaded, you have 6.00 GiB',
            $list[0]->postList(1, 0)[0]['body'],
            'demote-user-fail',
        );
    }

    public function testNoDemotion(): void {
        $userMan = new Manager\User();
        $db      = DB::DB();
        $db->prepared_query("
            UPDATE users_main SET
                created = created - INTERVAL 8 DAY
            WHERE ID = ?
            ", $this->user->id
        );
        $this->user->setField('leech_upload', 12 * 1024 ** 3)->modify();
        $userMan->promote();
        $this->assertEquals(
            'Member',
            $this->user->flush()->userclassName(),
            'promote-userclass-name'
        );
        // drop to 0.8 ratio
        $this->user->setField('leech_download', 15 * 1024 ** 3)->modify();
        $this->assertEquals(
            0,
            $userMan->demote(),
            'demote-ratio-no',
        );
        // drop to 0.5 ratio
        $this->user->setField('leech_download', 24 * 1024 ** 3)->modify();
        $this->assertEquals(
            1,
            $userMan->demote(),
            'demote-ratio-yes',
        );

        $inbox = $this->user->inbox()->setUnreadFirst(true);
        $list  = $inbox->messageList(new Manager\PM($this->user), 2, 0);
        $this->assertEquals(
            'You have been demoted to User',
            $list[0]->subject(),
            'demote-ratio-subject',
        );
        $body = $list[0]->postList(1, 0)[0]['body'];
        $this->assertStringContainsString(
            '✅ 10.00 GiB uploaded, you have 12.00 GiB',
            $list[0]->postList(1, 0)[0]['body'],
            'demote-ratio-upload',
        );
        $this->assertStringContainsString(
            '❌ 0.6 ratio required, you have 0.5000',
            $list[0]->postList(1, 0)[0]['body'],
            'demote-user-demote-ratio-ratio',
        );

        $this->user->setField('leech_upload', 501 * 1024 ** 3)->modify();
        $db->prepared_query('
            UPDATE user_summary SET upload_total = 50 WHERE user_id = ?
            ', $this->user->id
        );
        $db->prepared_query("
            UPDATE users_main SET
                created = created - INTERVAL 4 WEEK
            WHERE ID = ?
            ", $this->user->id
        );
        $this->user->flush();
        $userMan->promote();
        $list = $inbox->messageList(new Manager\PM($this->user), 1, 0);
        $this->assertEquals(
            'You have been promoted to Elite',
            $list[0]->subject(),
            'promote-user-elite',
        );

        $db->prepared_query('
            UPDATE user_summary SET upload_total = 501, unique_group_total = 501 WHERE user_id = ?
            ', $this->user->id
        );
        $db->prepared_query("
            UPDATE users_main SET
                created = created - INTERVAL 8 WEEK
            WHERE ID = ?
            ", $this->user->id
        );
        $this->user->flush();
        $userMan->promote();
        $list = $inbox->messageList(new Manager\PM($this->user), 1, 0);
        $this->assertEquals(
            'You have been promoted to Power TM',
            $list[0]->subject(),
            'promote-user-power-tm',
        );

        $db->prepared_query('
            UPDATE user_summary SET unique_group_total = 499 WHERE user_id = ?
            ', $this->user->id
        );
        $this->user->flush();
        $userMan->demote();
        $list = $inbox->messageList(new Manager\PM($this->user), 1, 0);
        $this->assertEquals(
            'You have been demoted to Torrent Master',
            $list[0]->subject(),
            'demote-user-tm',
        );
        $body = $list[0]->postList(1, 0)[0]['body'];
        $this->assertStringContainsString(
            'You have been demoted from Power TM to Torrent Master',
            $body,
            'demote-tm-message',
        );
        $this->assertStringContainsString(
            '✅ 500.00 GiB uploaded, you have 501.00 GiB',
            $body,
            'demote-tm-upload-size',
        );
        $this->assertStringContainsString(
            '✅ 0.95 ratio required, you have 20.8750',
            $body,
            'demote-tm-ratio',
        );
        $this->assertStringContainsString(
            '✅ 500 uploads required, you have 501',
            $body,
            'demote-tm-upload-total',
        );
        $this->assertStringContainsString(
            '❌ 500 Unique groups required, you have 499',
            $body,
            'demote-tm-upload-unique',
        );

        $db->prepared_query('
            UPDATE user_summary SET
                upload_total         = 2000,
                unique_group_total   =  500,
                perfect_flac_total   =  500,
                perfecter_flac_total = 2000
            WHERE user_id = ?
            ', $this->user->id
        );
        $db->prepared_query("
            UPDATE users_main SET
                created = created - INTERVAL 12 WEEK
            WHERE ID = ?
            ", $this->user->id
        );
        $this->user->setField('leech_upload', 2048 * 1024 ** 3)->modify();
        $this->assertEquals(3, $userMan->promote(), 'promote-to-ultimate');
        $this->assertEquals(
            $this->user->flush()->primaryClass(),
            ULTIMATE_TM,
            'user-is-ultimate',
        );

        $db->prepared_query('
            UPDATE user_summary SET perfecter_flac_total = 1999 WHERE user_id = ?
            ', $this->user->id
        );
        $this->user->flush();
        $userMan->demote();
        $list = $inbox->messageList(new Manager\PM($this->user), 1, 0);
        $this->assertEquals(
            'You have been demoted to Elite TM',
            $list[0]->subject(),
            'demote-user-utm',
        );
        $body = $list[0]->postList(1, 0)[0]['body'];
        $this->assertStringContainsString(
            '✅ 2,000 uploads required, you have 2,000',
            $body,
            'demote-utm-upload-total',
        );
        $this->assertStringContainsString(
            '❌ 2,000 "Perfecter" FLACs required, you have 1,999',
            $body,
            'demote-utm-upload-unique',
        );
    }

    public function testUserBuffer(): void {
        $this->user->setField('leech_upload', 8 * 1024 ** 3)->modify();
        $manager = new Manager\User();
        [$ratio, $buffer] = $manager->userBuffer($this->user);
        $this->assertEqualsWithDelta(
            0.6,
            $ratio,
            0.0001,
            'buffer-infin-ratio',
        );
        $this->assertEqualsWithDelta(
            $this->user->uploadedSize() / $ratio,
            $buffer,
            0.0001,
            'buffer-infin-buffer',
        );

        $this->user->setField('leech_download', 4 * 1024 ** 3)->modify();
        [$ratio, $buffer] = $manager->userBuffer($this->user);
        $this->assertEqualsWithDelta(
            $this->user->uploadedSize() / $ratio - $this->user->downloadedSize(),
            $buffer,
            0.01,
            'buffer-down-buffer',
        );
    }

    public function testNextClass(): void {
        $manager = new Manager\User();
        $next    = $manager->userNextClass($this->user);
        $this->assertIsArray($next, 'user-next-array');
        $this->assertEquals('Member', $next['class'], 'user-next-class-is-member');

        $goal = $next['goal'];
        $this->assertIsArray($goal, 'user-next-requirements-array');
        $this->assertCount(3, $goal, 'user-next-requirements-count');
        $this->assertStringContainsString('100%', $goal['Ratio']['percent'],  'user-next-ratio');
        $this->assertStringContainsString('0%',   $goal['Time']['percent'],   'user-next-time');
        $this->assertStringContainsString('30%',  $goal['Upload']['percent'], 'user-next-upload');

        $this->user->setField('created', date('Y-m-d H:i:s', strtotime('-2 day')))->modify();
        $next = $manager->userNextClass($this->user);
        $this->assertIsArray($next, 'user-next-requirements-next-array');
        $this->assertStringContainsString('29%', $next['goal']['Time']['percent'], 'user-next-closer-time');
        $this->user->setField('created', date('Y-m-d H:i:s', strtotime('-7 day')))->modify();
        $next = $manager->userNextClass($this->user);
        $this->assertIsArray($next, 'user-next-requirements-next-2-array');
        $this->assertStringContainsString('100%', $next['goal']['Time']['percent'], 'user-next-has-time');

        $this->user->addBounty(7 * 1024 * 1024 * 1024);
        $this->user->stats()->flush();
        $next = $manager->userNextClass($this->user);
        $this->assertIsArray($next, 'user-next-requirements-next-3-array');
        $this->assertStringContainsString('100%',  $next['goal']['Upload']['percent'], 'user-next-has-upload');

        $manager->promote();
        $this->assertEquals('Member', $this->user->flush()->userclassName(), 'user-promoted-to-member');

        $this->user->setField('PermissionId', SYSOP)->modify();
        $this->assertNull($manager->userNextClass($this->user), 'user-next-class-is-null');
    }
}
