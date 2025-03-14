<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\UserAuditEvent;

class InviteTest extends TestCase {
    protected User $user;
    protected User $invitee;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('invite.' . randomString(10), 'invite');
    }

    public function tearDown(): void {
        if (isset($this->invitee)) {
            $this->invitee->remove();
        }
        $this->user->remove();
    }

    public function testInviter(): void {
        $this->assertFalse($this->user->disableInvites(), 'invite-not-disabled');
        $this->assertFalse($this->user->permitted('users_view_invites'),          'invite-users-view-invites');
        $this->assertFalse($this->user->permitted('site_send_unlimited_invites'), 'invite-site-send-unlimited-invites');
        $this->assertFalse($this->user->permitted('site_can_invite_always'),      'invite-site-can-invite-always');
        $this->assertFalse($this->user->permitted('users_invite_notes'),          'invite-users-invite-notes');
        $this->assertFalse($this->user->permitted('users_edit_invites'),          'invite-users-edit-invites');
        $this->assertFalse($this->user->permitted('admin_manage_invite_source'),  'invite-admin-manage-invite-source');

        $this->assertEquals(0, $this->user->stats()->invitedTotal(), 'invite-total-0');
        $this->assertEquals(0, $this->user->unusedInviteTotal(), 'invite-unused-0');
        $this->assertEquals(0, $this->user->invite()->pendingTotal(), 'invite-pending-0-initial');

        // USER cannot invite, but MEMBER can
        $this->assertFalse($this->user->canInvite(),  'invite-cannot-invite');
        $this->assertFalse($this->user->canPurchaseInvite(),  'invite-cannot-purchase');
        $this->user->setField('PermissionID', MEMBER)->modify();
        $this->assertTrue($this->user->canInvite(),  'invite-can-invite');
        $this->assertTrue($this->user->canPurchaseInvite(),  'invite-can-now-purchase');

        // add some BP to play with
        $bonus = new User\Bonus($this->user);
        $this->assertEquals(1, $bonus->addPoints(1_000_000), 'invite-add-bp');
        $this->assertTrue($bonus->purchaseInvite(),  'invite-purchase-invite');
        $this->assertEquals(1, $this->user->unusedInviteTotal(), 'invite-unused-1');

        $this->assertTrue($this->user->invite()->issueInvite(), 'invite-issue-true');
        $this->assertFalse($this->user->invite()->issueInvite(), 'invite-decrement-none-left');
        $this->user->setField('Invites', 1)->modify();

        // invite someone
        $this->assertTrue((new Stats\Users())->newUsersAllowed($this->user), 'invite-new-users-allowed');
        $manager = new Manager\Invite();
        $email = randomString(10) . "@invitee.example.com";
        $this->assertFalse($manager->emailExists($this->user, $email), 'invitee-email-not-pending');
        $invite = $manager->create($this->user, $email, 'unittest notes', 'unittest reason', '');
        $this->assertInstanceOf(Invite::class, $invite, 'invite-invitee-created');
        $this->assertEquals(1, $this->user->invite()->pendingTotal(), 'invite-pending-1');
        $this->assertEquals(0, $this->user->unusedInviteTotal(), 'invite-unused-0-again');
        $this->assertEquals($invite->email(), $this->user->invite()->pendingList()[$invite->key()]['email'], 'invite-invitee-email');

        // respond to invite
        $this->assertTrue($manager->inviteExists($invite->key()), 'invite-key-found');
        $this->invitee = \GazelleUnitTest\Helper::makeUserByInvite('invitee.' . randomString(6), $invite->key());
        $this->assertInstanceOf(User::class, $this->invitee, 'invitee-class');
        $this->assertEquals($this->user->id(), $this->invitee->inviter()->id(), 'invitee-invited-by');
        $this->assertEquals($this->user->id(), $this->invitee->inviterId(), 'invitee-invited-id');
        $this->assertEquals(1, $this->user->stats()->invitedTotal(), 'invite-total-1');
        $this->assertEquals(0, $this->user->flush()->invite()->pendingTotal(), 'invite-pending-back-to-0');
        $inviteList = $this->user->invite()->page('um.ID', 'ASC', 1, 0);
        $this->assertCount(1, $inviteList, 'invite-invite-list-total');
        $this->assertEquals($this->invitee->id(), $inviteList[0], 'invite-list-has-invitee');

        $this->assertTrue($this->invitee->isUnconfirmed(), 'invitee-unconfirmed');
        $this->assertInstanceOf(
            User::class,
            (new Manager\User())->findByAnnounceKey($this->invitee->announceKey()),
            'invitee-confirmable'
        );

        // invite tree functionality
        $inviteTree = new User\InviteTree($this->user);
        $this->assertInstanceOf(User\InviteTree::class, $inviteTree, 'invite-tree-ctor');
        $this->assertTrue($inviteTree->hasInvitees(), 'invite-tree-has-invitees');
        $list = $inviteTree->inviteeList();
        $this->assertCount(1, $list, 'invite-tree-list');
        $this->assertEquals($this->invitee->id(), $list[0], 'invite-tree-user-id');

        // new invite tree functionality
        $summary = $inviteTree->summary();
        $this->assertCount(10, array_keys($summary), 'invite-tree-summary-keys');
        $this->assertEquals(1, $summary['branch'], 'invite-tree-branch');
        $this->assertEquals(1, $summary['depth'], 'invite-tree-depth');
        $this->assertEquals(1, $summary['total'], 'invite-tree-total');
        $this->assertEquals(0, $summary['disabled'], 'invite-tree-disabled');
        $this->assertEquals(0, $summary['donor'], 'invite-tree-donor');
        $this->assertEquals(0, $summary['downloaded'], 'invite-tree-downloaded');
        $this->assertEquals(0, $summary['paranoid'], 'invite-tree-paranoid');
        $this->assertEquals(STARTING_UPLOAD, $summary['direct']['up'], 'invite-tree-direct-up');
        $this->assertEquals(STARTING_UPLOAD, $summary['uploaded'], 'invite-tree-uploaded');
        $this->assertEquals(['User' => 1], $summary['userclass'], 'invite-tree-userclass');
        $this->assertCount(1, $inviteTree->inviteTree(), 'invite-tree-tree');
    }

    public function testAncestry(): void {
        $manager = new Manager\Invite();
        $userMan = new Manager\User();

        $this->user->setField('PermissionID', MEMBER)
            ->setField('Invites', 1)
            ->modify();
        $invite = $manager->create(
            user:   $this->user,
            email:  randomString(10) . "@tree.example.com",
            notes:  'phpunit tree notes',
            reason: '',
            source: '',
        );
        $this->invitee = (new UserCreator())
            ->setUsername('create.' . randomString(6))
            ->setEmail(randomString(6) . '@example.com')
            ->setPassword(randomString(10))
            ->setInviteKey($invite->key())
            ->create();

        $this->assertEquals(
            $this->user->username(),
            $userMan->ancestry($this->invitee)[0]->username(),
            'invite-ancestry'
        );
    }

    public function testManipulate(): void {
        $userMan = new Manager\User();
        $tracker = new Tracker();

        $this->assertEquals(
            "No action specified",
            (new User\InviteTree($this->user))
            ->manipulate(
                "",
                false,
                false,
                $tracker,
                $this->user,
                $userMan,
            ),
            'invite-tree-manip-none'
        );

        $this->assertEquals(
            "No invitees for {$this->user->username()}",
            (new User\InviteTree($this->user))
            ->manipulate(
                "phpunit invite tree comment",
                false,
                false,
                $tracker,
                $this->user,
                $userMan,
            ),
            'invite-tree-manip-comment'
        );

        $this->user
            ->setField('PermissionID', MEMBER)
            ->setField('Invites', 1)
            ->modify();
        $manager = new Manager\Invite();
        $invite = $manager->create(
            user:   $this->user,
            email:  randomString(10) . "@tree.example.com",
            notes:  'phpunit tree notes',
            reason: '',
            source: '',
        );
        $this->invitee = (new UserCreator())
            ->setUsername('create.' . randomString(6))
            ->setEmail(randomString(6) . '@example.com')
            ->setPassword(randomString(10))
            ->setInviteKey($invite->key())
            ->create();

        $this->assertStringContainsString(
            "Commented entire tree (1 user)",
            (new User\InviteTree($this->user))
            ->manipulate(
                "phpunit invite tree comment",
                false,
                false,
                $tracker,
                $this->user,
                $userMan,
            ),
            'invite-tree-manip-comment'
        );
        $this->assertStringContainsString(
            "Invite Tree comment on {$this->user->username()} by {$this->user->username()}\nReason: phpunit invite tree comment\n",
            $this->user->staffNotes(),
            'invite-tree-manip-user-comment',
        );
        // need to flush to pick up the out-of-band changes
        $this->assertStringContainsString(
            "Invite Tree comment on {$this->user->username()} by {$this->user->username()}\nReason: phpunit invite tree comment\n",
            $this->invitee->flush()->staffNotes(),
            'invite-tree-manip-inv-comment',
        );

        $this->assertStringContainsString(
            "Revoked invites for entire tree (1 user)",
            (new User\InviteTree($this->user))
            ->manipulate(
                "",
                false,
                true,
                $tracker,
                $this->user,
                $userMan,
            ),
            'invite-tree-manip-revoke'
        );
        $this->assertTrue($this->invitee->flush()->disableInvites(), 'invite-tree-inv-invites');
        $this->assertStringContainsString(
            "Invite Tree invites removed on {$this->user->username()} by {$this->user->username()}\n",
            $this->user->staffNotes(),
            'invite-tree-manip-user-revoke',
        );
        $this->assertStringContainsString(
            "Invite Tree invites removed on {$this->user->username()} by {$this->user->username()}\n",
            $this->invitee->staffNotes(),
            'invite-tree-manip-inv-revoke',
        );

        $this->assertStringContainsString(
            "Banned entire tree (1 user)",
            (new User\InviteTree($this->user))
            ->manipulate(
                "",
                true,
                false,
                $tracker,
                $this->user,
                $userMan,
            ),
            'invite-tree-manip-revoke'
        );
        $eventList = array_values(array_filter(
            $this->invitee->auditTrail()->eventList(),
            fn ($e) => $e['event'] === UserAuditEvent::invite->value,
        ));
        $this->assertStringContainsString('Invite Tree ban on ', $eventList[0]['note'], 'invite-tree-event-0');
        $this->assertStringContainsString('Invite Tree invites removed on ', $eventList[1]['note'], 'invite-tree-event-1');
        $this->assertStringContainsString('Invite Tree comment on ', $eventList[2]['note'], 'invite-tree-event-2');
        $this->assertTrue($this->invitee->flush()->isDisabled(), 'invite-tree-inv-disabled');
        $this->assertStringContainsString(
            "Invite Tree ban on {$this->user->username()} by {$this->user->username()}\n",
            $this->user->staffNotes(),
            'invite-tree-manip-user-ban',
        );
        $this->assertStringContainsString(
            "Invite Tree ban on {$this->user->username()} by {$this->user->username()}\n",
            $this->invitee->staffNotes(),
            'invite-tree-manip-inv-ban',
        );
    }

    public function testInviteSource(): void {
        $inviteSourceMan = new Manager\InviteSource();
        $userMan = new Manager\User();
        $this->user->setField('PermissionID', MEMBER)->modify();
        $this->user->addClasses([
            (int)current(array_filter($userMan->classList(), fn($class) => $class['Name'] == 'Recruiter'))['ID']
        ]);
        $this->assertNull($inviteSourceMan->findSourceNameByUser($this->user), 'invite-source-inviter-source');

        // set up an invite source for an inviter
        $initialSource = $inviteSourceMan->inviterConfiguration($this->user);
        $this->assertIsArray($initialSource, 'invite-source-list-initial');

        $sourceId = $inviteSourceMan->create('pu.' . randomString(6));
        $this->assertIsInt($sourceId, 'invite-source-create');
        $this->assertEquals(
            1,
            $inviteSourceMan->modifyInviterConfiguration($this->user, [$sourceId]),
            'invite-source-inviter-assign'
        );
        $this->assertEquals(
            1 + count($initialSource),
            count($inviteSourceMan->inviterConfiguration($this->user)),
            'invite-source-inviter-assigned'
        );
        $this->assertCount(
            1,
            array_filter(
                $inviteSourceMan->summaryByInviter(),
                fn($s) => $s['user_id'] === $this->user->id()
            ),
            'invite-source-inviter-summary'
        );
        $config = $inviteSourceMan->inviterConfigurationActive($this->user);
        $this->assertCount(1, $config, 'invite-source-inviter-count');
        $sourceName = $config[0]['name'];
        $this->assertIsString($sourceName, 'invite-source-source-name');

        // now invite a user from a designated source
        $profile = 'https://example.com/user/' . random_int(1000, 9999);
        $manager = new Manager\Invite();
        $invite = $manager->create(
            user: $this->user,
            email: randomString(10) . "@invitee.src.example.com",
            notes: 'phpunit notes',
            reason: $profile,
            source: $sourceName,
        );
        $this->assertEquals(
            1,
            $inviteSourceMan->createPendingInviteSource($sourceId, $invite->key()),
            'invite-source-create-pending'
        );

        // create auser from the invite
        $this->invitee = (new UserCreator())
            ->setUsername('create.' . randomString(6))
            ->setEmail(randomString(6) . '@example.com')
            ->setPassword(randomString(10))
            ->setInviteKey($invite->key())
            ->create();
        $this->assertEquals(
            $sourceName,
            $inviteSourceMan->findSourceNameByUser($this->invitee),
            'invitee-invite-source'
        );
        $this->assertEquals($profile, $this->invitee->externalProfile()->profile(), 'invite-source-profile');
        $this->assertStringContainsString('phpunit notes', $this->invitee->staffNotes(), 'invite-recruiter-notes');

        $inviteeList = $inviteSourceMan->userSource($this->user);
        $this->assertCount(1, $inviteeList, 'invite-source-invited-list');
        $this->assertEquals(
            [
                "user_id"          => $this->invitee->id(),
                "invite_source_id" => $sourceId,
                "name"             => $sourceName,
            ],
            $inviteeList[$this->invitee->id()],
            'invite-source-invited-invitee'
        );

        $usage = current(array_filter(
            $inviteSourceMan->usageList(),
            fn($s) => $s['invite_source_id'] === $sourceId
        ));
        $this->assertEquals(
            [
                "invite_source_id" => $sourceId,
                "name"             => $sourceName,
                "inviter_total"    => 1,
                "user_total"       => 1,
            ],
            $usage,
            'invite-source-usage'
        );

        // change the invitee source and profile
        $newProfile = $profile . "/new";
        $new = [
            $this->invitee->id() => [
                "user_id" => $this->invitee->id(),
                "source"  => 0,
                "profile" => $newProfile,
            ]
        ];
        $this->assertEquals(
            2,
            $inviteSourceMan->modifyInviteeSource($new),
            'invite-modify-invitee'
        );
        $this->assertEquals($newProfile, $this->invitee->externalProfile()->profile(), 'invite-source-new-profile');
        $inviteeList = $inviteSourceMan->userSource($this->user);
        $this->assertEquals(
            [
                "user_id"          => $this->invitee->id(),
                "invite_source_id" => null,
                "name"             => null,
            ],
            $inviteeList[$this->invitee->id()],
            'invite-source-invitee-unsourced'
        );

        // tidy up
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM invite_source_pending WHERE invite_source_id = ?
            ", $sourceId
        );
        $db->prepared_query("
            DELETE FROM user_has_invite_source WHERE invite_source_id = ?
            ", $sourceId
        );

        $this->assertEquals(
            1,
            $inviteSourceMan->modifyInviterConfiguration($this->user, []),
            'invite-source-user-clear'
        );
        $this->assertEquals(1, $inviteSourceMan->remove($sourceId), 'invite-source-create-remove');
    }

    public function testRevokeInvite(): void {
        $this->user->setField('Invites', 1)->modify();

        $manager = new Manager\Invite();
        $email = randomString(10) . "@invitee.example.com";
        $this->assertFalse($manager->emailExists($this->user, $email), 'invitee-email-not-pending');
        $invite = $manager->create($this->user, $email, 'unittest notes', 'unittest reason', '');
        $this->assertFalse($this->user->invite()->revoke('nosuchthing'), 'invite-revoke-inexistant');
        $this->assertTrue($this->user->invite()->revoke($invite->key()), 'invite-revoke-existing');
        $this->assertEquals(1, $this->user->unusedInviteTotal(), 'invite-unused-1');
    }

    public function testEtm(): void {
        $this->user->setField('PermissionID', ELITE_TM)->modify();

        $this->assertEquals('Elite TM', $this->user->userclassName(),              'etm-userclass-check');
        $this->assertTrue($this->user->permitted('site_send_unlimited_invites'),   'etm-site-send-unlimited-invites');
        $this->assertFalse($this->user->permitted('site_can_invite_always'),       'etm-site-can-invite-always');
        $this->assertTrue((new Stats\Users())->newUsersAllowed($this->user), 'etm-new-users-allowed');
        $this->assertTrue($this->user->canPurchaseInvite(),                        'etm-can-purchase-invites');

        $invite = (new Manager\Invite())->create($this->user, randomString(10) . "@etm.example.com", 'unittest notes', 'unittest reason', '');
        $this->assertInstanceOf(Invite::class, $invite, 'etm-issued-invite');
    }
}
