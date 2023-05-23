<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class InviteTest extends TestCase {
    public function testInvite(): void {
        $user = Helper::makeUser('invite.' . randomString(6), 'invite');

        $this->assertFalse($user->disableInvites(), 'invite-not-disabled');
        $this->assertFalse($user->permitted('users_view_invites'),          'invite-users-view-invites');
        $this->assertFalse($user->permitted('site_send_unlimited_invites'), 'invite-site-send-unlimited-invites');
        $this->assertFalse($user->permitted('site_can_invite_always'),      'invite-site-can-invite-always');
        $this->assertFalse($user->permitted('users_invite_notes'),          'invite-users-invite-notes');
        $this->assertFalse($user->permitted('users_edit_invites'),          'invite-users-edit-invites');
        $this->assertFalse($user->permitted('admin_manage_invite_source'),  'invite-admin-manage-invite-source');

        $this->assertEquals(0, $user->stats()->invitedTotal(), 'invite-total-0');
        $this->assertEquals(0, $user->unusedInviteTotal(), 'invite-unused-0');
        $this->assertEquals(0, $user->pendingInviteCount(), 'invite-pending-0-initial');

        // USER cannot invite, but MEMBER can
        $this->assertFalse($user->canPurchaseInvite(),  'invite-cannot-purchase');
        $user->setUpdate('PermissionID', MEMBER)->modify();
        $this->assertTrue($user->canPurchaseInvite(),  'invite-can-now-purchase');

        // add some BP to play with
        $bonus = new Gazelle\User\Bonus($user);
        $this->assertEquals(1, $bonus->addPoints(1_000_000), 'invite-add-bp');
        $this->assertTrue($bonus->purchaseInvite(),  'invite-purchase-invite');
        $this->assertEquals(1, $user->unusedInviteTotal(), 'invite-unused-1');

        // invite someone
        $this->assertTrue((new Gazelle\Stats\Users)->newUsersAllowed($user), 'invite-new-users-allowed');
        $manager = new Gazelle\Manager\Invite;
        $email = randomString(10) . "@invitee.example.com";
        $this->assertFalse($manager->emailExists($user, $email), 'invitee-email-not-pending');
        $invite = $manager->create($user, $email, 'unittest', '');
        $this->assertInstanceOf(Gazelle\Invite::class, $invite, 'invite-invitee-created');
        $this->assertEquals(1, $user->pendingInviteCount(), 'invite-pending-1');
        $this->assertEquals(0, $user->unusedInviteTotal(), 'invite-unused-0-again');
        $this->assertEquals($invite->email(), $user->pendingInviteList()[$invite->key()]['email'], 'invite-invitee-email');

        // respond to invite
        $this->assertTrue($manager->inviteExists($invite->key()), 'invite-key-found');
        $invitee = Helper::makeUserByInvite('invitee.' . randomString(6), $invite->key());
        $this->assertInstanceOf(Gazelle\User::class, $invitee, 'invitee-class');
        $this->assertEquals($user->id(), $invitee->inviter()->id(), 'invitee-invited-by');
        $this->assertEquals(1, $user->stats()->invitedTotal(), 'invite-total-1');
        $this->assertEquals(0, $user->flush()->pendingInviteCount(), 'invite-pending-back-to-0');
        $inviteList = $user->inviteList('um.ID', 'ASC');
        $this->assertCount(1, $inviteList, 'invite-invite-list-total');
        $this->assertEquals($invitee->id(), $inviteList[0]['user_id'], 'invite-list-has-invitee');

        $this->assertTrue($invitee->isUnconfirmed(), 'invitee-unconfirmed');
        $this->assertInstanceOf(Gazelle\User::class, (new Gazelle\Manager\User)->findByAnnounceKey($invitee->announceKey()), 'invitee-confirmable');
        $this->assertEquals(1, $user->remove(), 'inviter-removed');
        $this->assertEquals(1, $invitee->remove(), 'invitee-removed');
    }

    public function testEtm(): void {
        $etm = Helper::makeUser('etm.' . randomString(6), 'etm')
            ->setUpdate('PermissionID', ELITE_TM);
        $etm->modify();

        $this->assertEquals('Elite TM', $etm->userclassName(),              'etm-userclass-check');
        $this->assertTrue($etm->permitted('site_send_unlimited_invites'),   'etm-site-send-unlimited-invites');
        $this->assertFalse($etm->permitted('site_can_invite_always'),       'etm-site-can-invite-always');
        $this->assertTrue((new Gazelle\Stats\Users)->newUsersAllowed($etm), 'etm-new-users-allowed');
        $this->assertTrue($etm->canPurchaseInvite(),                        'etm-can-purchase-invites');

        $invite = (new Gazelle\Manager\Invite)->create($etm, randomString(10) . "@etm.example.com", 'unittest', '');
        $this->assertInstanceOf(Gazelle\Invite::class, $invite, 'etm-issued-invite');
        $this->assertEquals(1, $etm->remove(), 'etm-removed');
    }
}
