<?php

namespace Gazelle;

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UserTest extends TestCase {

    protected Manager\User $userMan;

    public function setUp(): void {
        $this->userMan = new Manager\User;
    }

    public function tearDown(): void {
    }

    public function testAdmin() {
        $admin = $this->userMan->find('@admin');
        $this->assertTrue($admin->isStaff(), 'admin-is-admin');
        $this->assertTrue($admin->permitted('site_upload'), 'admin-permitted-site_upload');
        $this->assertTrue($admin->permitted('site_debug'), 'admin-permitted-site_debug');
        $this->assertTrue($admin->permittedAny('site_analysis', 'site_debug'), 'admin-permitted-any-site_analysis-site_debug');
    }

    /**
     * @depends testUser
     */
    public function testAttr() {
        $user = $this->userMan->findById(2);

        $this->assertFalse($user->hasUnlimitedDownload(), 'uattr-hasUnlimitedDownload');
        $user->toggleUnlimitedDownload(true);
        $this->assertTrue($user->hasUnlimitedDownload(), 'uattr-not-hasUnlimitedDownload');

        $this->assertTrue($user->hasAcceptFL(), 'uattr-has-FL');
        $user->toggleAcceptFL(false);
        $this->assertFalse($user->hasAcceptFL(), 'uattr-has-not-FL');

        $this->assertNull($user->option('nosuchoption'), 'uattr-nosuchoption');

        $this->assertEquals($user->avatarMode(), 0, 'uattr-avatarMode');
        $this->assertEquals($user->bonusPointsTotal(), 0, 'uattr-bp');
        $this->assertEquals($user->downloadedSize(), 0, 'uattr-starting-download');
        $this->assertEquals($user->postsPerPage(), POSTS_PER_PAGE, 'uattr-ppp');
        $this->assertEquals($user->uploadedSize(), STARTING_UPLOAD, 'uattr-starting-upload');

        $this->assertFalse($user->disableAvatar(), 'uattr-disableAvatar');
        $this->assertFalse($user->disableBonusPoints(), 'uattr-disableBonusPoints');
        $this->assertFalse($user->disableForums(), 'uattr-disableForums');
        $this->assertFalse($user->disableInvites(), 'uattr-disableInvites');
        $this->assertFalse($user->disableIRC(), 'uattr-disableIRC');
        $this->assertFalse($user->disablePm(), 'uattr-disablePm');
        $this->assertFalse($user->disablePosting(), 'uattr-disablePosting');
        $this->assertFalse($user->disableRequests(), 'uattr-disableRequests');
        $this->assertFalse($user->disableTagging(), 'uattr-disableTagging');
        $this->assertFalse($user->disableUpload(), 'uattr-disableUpload');
        $this->assertFalse($user->disableWiki(), 'uattr-disableWiki');

        $this->assertFalse($user->hasAttr('disable-forums'), 'uattr-hasAttr-disable-forums-no');
        $user->toggleAttr('disable-forums', true);
        $this->assertTrue($user->hasAttr('disable-forums'), 'uattr-toggle-disable-forums');
        $this->assertTrue($user->disableForums(), 'uattr-hasAttr-disable-forums-yes');

        $this->assertContainsOnly('null', $user->donorAvatar());
    }

    /**
     * @depends testUser
     */
    public function testPassword() {
        $user = $this->userMan->findById(2);
        $this->assertTrue($user->validatePassword('password'), 'utest-password-validate');
        $password = randomString(30);
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $this->assertTrue($user->updatePassword($password, '0.0.0.0'), 'utest-password-modify');
        $this->assertTrue($user->validatePassword($password), 'utest-password-validate-new');
        $this->assertCount(1, $user->passwordHistory(), 'utest-password-history');
        $this->assertEquals($user->passwordCount(), 1, 'utest-password-count');
    }

    public function testUser() {
        $user = $this->userMan->findById(2);
        $this->assertEquals($user->username(), 'user', 'utest-username');
        $this->assertEquals($user->username(), $user->flush()->username(), 'utest-flush-username');
        $this->assertEquals($user->email(), 'user@example.com', 'utest-email');

        $this->assertEquals($user->primaryClass(), USER, 'utest-primary-class');
        $this->assertEquals($user->inboxUnreadCount(), 0, 'utest-inbox-unread');
        $this->assertEquals($user->allowedPersonalCollages(), 0, 'utest-personal-collages-allowed');
        $this->assertEquals($user->paidPersonalCollages(), 0, 'utest-personal-collages-paid');
        $this->assertEquals($user->activePersonalCollages(), 0, 'utest-personal-collages-active');
        $this->assertEquals($user->collagesCreated(), 0, 'utest-collage-created');
        $this->assertEquals($user->pendingInviteCount(), 0, 'utest-personal-collages-active');
        $this->assertEquals($user->seedingSize(), 0, 'utest-personal-collages-active');

        $this->assertTrue($user->isEnabled(), 'utest-is-enabled');
        $this->assertTrue($user->isVisible(), 'utest-is-visble');
        $this->assertTrue($user->canLeech(), 'can-leech');
        $this->assertTrue($user->permitted('site_upload'), 'utest-permitted-site-upload');
        $this->assertTrue($user->permittedAny('site_upload', 'site_debug'), 'utest-permittedAny-site-upload-site-debug');

        $this->assertFalse($user->isDisabled(), 'utest-is-disabled');
        $this->assertFalse($user->isFLS(), 'utest-is-fls');
        $this->assertFalse($user->isInterviewer(), 'utest-is-interviewer');
        $this->assertFalse($user->isLocked(), 'utest-is-locked');
        $this->assertFalse($user->isRecruiter(), 'utest-is-recruiter');
        $this->assertFalse($user->isStaff(), 'utest-is-staff');
        $this->assertFalse($user->isStaffPMReader(), 'utest-is-staff-pm-reader');
        $this->assertFalse($user->isUnconfirmed(), 'utest-is-confirmed');
        $this->assertFalse($user->isWarned(), 'utest-is-warned');
        $this->assertFalse($user->canCreatePersonalCollage(), 'utest-personal-collage-create');
        $this->assertFalse($user->permitted('site_debug'), 'utest-permitted-site-debug');
        $this->assertFalse($user->permittedAny('site_analysis', 'site_debug'), 'utest-permittedAny-site-analysis-site-debug');

        $this->assertNull($user->warningExpiry(), 'utest-warning-expiry');

        $this->assertCount(0, $user->announceKeyHistory(), 'utest-announce-key-history');
    }
}
