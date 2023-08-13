<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class InviteTest extends TestCase {
    protected \Gazelle\User $user;
    protected \Gazelle\User $invitee;

    public function setUp(): void {
        $this->user = Helper::makeUser('invite.' . randomString(10), 'invite');
    }

    public function tearDown(): void {
        if (isset($this->invitee)) {
            $this->invitee->remove();
        }
        $this->user->remove();
    }

    public function testInvite(): void {
        $this->user = Helper::makeUser('invite.' . randomString(6), 'invite');

        $this->assertFalse($this->user->disableInvites(), 'invite-not-disabled');
        $this->assertFalse($this->user->permitted('users_view_invites'),          'invite-users-view-invites');
        $this->assertFalse($this->user->permitted('site_send_unlimited_invites'), 'invite-site-send-unlimited-invites');
        $this->assertFalse($this->user->permitted('site_can_invite_always'),      'invite-site-can-invite-always');
        $this->assertFalse($this->user->permitted('users_invite_notes'),          'invite-users-invite-notes');
        $this->assertFalse($this->user->permitted('users_edit_invites'),          'invite-users-edit-invites');
        $this->assertFalse($this->user->permitted('admin_manage_invite_source'),  'invite-admin-manage-invite-source');

        $this->assertEquals(0, $this->user->stats()->invitedTotal(), 'invite-total-0');
        $this->assertEquals(0, $this->user->unusedInviteTotal(), 'invite-unused-0');
        $this->assertEquals(0, $this->user->pendingInviteCount(), 'invite-pending-0-initial');

        // USER cannot invite, but MEMBER can
        $this->assertFalse($this->user->canInvite(),  'invite-cannot-invite');
        $this->assertFalse($this->user->canPurchaseInvite(),  'invite-cannot-purchase');
        $this->user->setField('PermissionID', MEMBER)->modify();
        $this->assertTrue($this->user->canInvite(),  'invite-can-invite');
        $this->assertTrue($this->user->canPurchaseInvite(),  'invite-can-now-purchase');

        // add some BP to play with
        $bonus = new Gazelle\User\Bonus($this->user);
        $this->assertEquals(1, $bonus->addPoints(1_000_000), 'invite-add-bp');
        $this->assertTrue($bonus->purchaseInvite(),  'invite-purchase-invite');
        $this->assertEquals(1, $this->user->unusedInviteTotal(), 'invite-unused-1');

        $this->assertTrue($this->user->decrementInviteCount(), 'invite-decrement-true');
        $this->assertFalse($this->user->decrementInviteCount(), 'invite-decrement-none-left');
        $this->user->setField('Invites', 1)->modify();

        // invite someone
        $this->assertTrue((new Gazelle\Stats\Users)->newUsersAllowed($this->user), 'invite-new-users-allowed');
        $manager = new Gazelle\Manager\Invite;
        $email = randomString(10) . "@invitee.example.com";
        $this->assertFalse($manager->emailExists($this->user, $email), 'invitee-email-not-pending');
        $invite = $manager->create($this->user, $email, 'unittest', '');
        $this->assertInstanceOf(Gazelle\Invite::class, $invite, 'invite-invitee-created');
        $this->assertEquals(1, $this->user->pendingInviteCount(), 'invite-pending-1');
        $this->assertEquals(0, $this->user->unusedInviteTotal(), 'invite-unused-0-again');
        $this->assertEquals($invite->email(), $this->user->pendingInviteList()[$invite->key()]['email'], 'invite-invitee-email');

        // respond to invite
        $this->assertTrue($manager->inviteExists($invite->key()), 'invite-key-found');
        $this->invitee = Helper::makeUserByInvite('invitee.' . randomString(6), $invite->key());
        $this->assertInstanceOf(Gazelle\User::class, $this->invitee, 'invitee-class');
        $this->assertEquals($this->user->id(), $this->invitee->inviter()->id(), 'invitee-invited-by');
        $this->assertEquals($this->user->id(), $this->invitee->inviterId(), 'invitee-invited-id');
        $this->assertEquals(1, $this->user->stats()->invitedTotal(), 'invite-total-1');
        $this->assertEquals(0, $this->user->flush()->pendingInviteCount(), 'invite-pending-back-to-0');
        $inviteList = $this->user->inviteList('um.ID', 'ASC');
        $this->assertCount(1, $inviteList, 'invite-invite-list-total');
        $this->assertEquals($this->invitee->id(), $inviteList[0]['user_id'], 'invite-list-has-invitee');

        $this->assertTrue($this->invitee->isUnconfirmed(), 'invitee-unconfirmed');
        $this->assertInstanceOf(Gazelle\User::class, (new Gazelle\Manager\User)->findByAnnounceKey($this->invitee->announceKey()), 'invitee-confirmable');
    }

    public function testRevokeInvite(): void {
        $this->user->setField('Invites', 1)->modify();

        $manager = new Gazelle\Manager\Invite;
        $email = randomString(10) . "@invitee.example.com";
        $this->assertFalse($manager->emailExists($this->user, $email), 'invitee-email-not-pending');
        $invite = $manager->create($this->user, $email, 'unittest', '');
        $this->assertFalse($this->user->revokeInvite('nosuchthing'), 'invite-revoke-inexistant');
        $this->assertTrue($this->user->revokeInvite($invite->key()), 'invite-revoke-existing');
        $this->assertEquals(1, $this->user->unusedInviteTotal(), 'invite-unused-1');
    }

    public function testEtm(): void {
        $this->user->setField('PermissionID', ELITE_TM)->modify();

        $this->assertEquals('Elite TM', $this->user->userclassName(),              'etm-userclass-check');
        $this->assertTrue($this->user->permitted('site_send_unlimited_invites'),   'etm-site-send-unlimited-invites');
        $this->assertFalse($this->user->permitted('site_can_invite_always'),       'etm-site-can-invite-always');
        $this->assertTrue((new Gazelle\Stats\Users)->newUsersAllowed($this->user), 'etm-new-users-allowed');
        $this->assertTrue($this->user->canPurchaseInvite(),                        'etm-can-purchase-invites');

        $invite = (new Gazelle\Manager\Invite)->create($this->user, randomString(10) . "@etm.example.com", 'unittest', '');
        $this->assertInstanceOf(Gazelle\Invite::class, $invite, 'etm-issued-invite');
    }
}
