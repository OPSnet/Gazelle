<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

use \Gazelle\Enum\AvatarDisplay;
use \Gazelle\Enum\AvatarSynthetic;

class UserTest extends TestCase {
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(6), 'user');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function modifyAvatarRender(AvatarDisplay $display, AvatarSynthetic $synthetic): int {
        $db = Gazelle\DB::DB();
        $db->prepared_query("
            UPDATE users_info SET SiteOptions = ? WHERE UserID = ?
            ", serialize(['DisableAvatars' => $display->value, 'Identicons' => $synthetic->value]), $this->user->id()
        );
        $affected = $db->affected_rows();
        $this->user->flush();
        return $affected;
    }

    public function testUserFind(): void {
        $userMan = new \Gazelle\Manager\User;
        $admin = $userMan->find('@admin');
        $this->assertTrue($admin->isStaff(), 'admin-is-admin');
        $this->assertTrue($admin->permitted('site_upload'), 'admin-permitted-site_upload');
        $this->assertTrue($admin->permitted('site_debug'), 'admin-permitted-site_debug');
        $this->assertTrue($admin->permittedAny('site_analysis', 'site_debug'), 'admin-permitted-any-site_analysis-site_debug');
    }

    public function testFindById(): void {
        $userMan = new \Gazelle\Manager\User;
        $user = $userMan->findById(2);
        $this->assertFalse($user->isStaff(), 'user-is-not-admin');
        $this->assertEquals($user->username(), 'user', 'user-username');
        $this->assertEquals($user->email(), 'user@example.com', 'user-email');
        $this->assertTrue($user->isEnabled(), 'user-is-enabled');
        $this->assertFalse($user->isUnconfirmed(), 'user-is-confirmed');
        $this->assertFalse($user->permittedAny('site_analysis', 'site_debug'), 'utest-permittedAny-site-analysis-site-debug');
    }

    public function testAttr(): void {
        $userMan = new \Gazelle\Manager\User;
        $this->assertFalse($this->user->hasUnlimitedDownload(), 'uattr-hasUnlimitedDownload');
        $this->user->toggleUnlimitedDownload(true);
        $this->assertTrue($this->user->hasUnlimitedDownload(), 'uattr-not-hasUnlimitedDownload');

        $this->assertTrue($this->user->hasAcceptFL(), 'uattr-has-FL');
        $this->user->toggleAcceptFL(false);
        $this->assertFalse($this->user->hasAcceptFL(), 'uattr-has-not-FL');

        $this->assertNull($this->user->option('nosuchoption'), 'uattr-nosuchoption');

        $this->assertEquals($this->user->avatarMode(), AvatarDisplay::show, 'uattr-avatarMode');
        $this->assertEquals($this->user->bonusPointsTotal(), 0, 'uattr-bp');
        $this->assertEquals($this->user->downloadedSize(), 0, 'uattr-starting-download');
        $this->assertEquals($this->user->postsPerPage(), POSTS_PER_PAGE, 'uattr-ppp');
        $this->assertEquals($this->user->uploadedSize(), STARTING_UPLOAD, 'uattr-starting-upload');
        $this->assertEquals($this->user->userclassName(), 'User', 'uattr-userclass-name');

        $this->assertFalse($this->user->disableAvatar(), 'uattr-disableAvatar');
        $this->assertFalse($this->user->disableBonusPoints(), 'uattr-disableBonusPoints');
        $this->assertFalse($this->user->disableForums(), 'uattr-disableForums');
        $this->assertFalse($this->user->disableInvites(), 'uattr-disableInvites');
        $this->assertFalse($this->user->disableIRC(), 'uattr-disableIRC');
        $this->assertFalse($this->user->disablePm(), 'uattr-disablePm');
        $this->assertFalse($this->user->disablePosting(), 'uattr-disablePosting');
        $this->assertFalse($this->user->disableRequests(), 'uattr-disableRequests');
        $this->assertFalse($this->user->disableTagging(), 'uattr-disableTagging');
        $this->assertFalse($this->user->disableUpload(), 'uattr-disableUpload');
        $this->assertFalse($this->user->disableWiki(), 'uattr-disableWiki');

        $this->assertFalse($this->user->hasAttr('disable-forums'), 'uattr-hasAttr-disable-forums-no');
        $this->user->toggleAttr('disable-forums', true);
        $this->assertTrue($this->user->hasAttr('disable-forums'), 'uattr-toggle-disable-forums');
        $this->assertTrue($this->user->disableForums(), 'uattr-hasAttr-disable-forums-yes');

        $this->assertFalse($this->user->downloadAsText(), 'uattr-download-as-torrent');
        $this->user->toggleAttr('download-as-text', true);
        $this->assertTrue($this->user->downloadAsText(), 'uattr-download-as-text');

        $this->assertTrue($this->user->notifyDeleteDownload(), 'uattr-pm-delete-download');
        $this->user->toggleAttr('no-pm-delete-download', true);
        $this->assertFalse($this->user->notifyDeleteDownload(), 'uattr-no-pm-delete-download');

        $this->assertTrue($this->user->notifyDeleteSeeding(), 'uattr-pm-delete-seed');
        $this->user->toggleAttr('no-pm-delete-seed', true);
        $this->assertFalse($this->user->notifyDeleteSeeding(), 'uattr-no-pm-delete-seed');

        $this->assertTrue($this->user->notifyDeleteSnatch(), 'uattr-pm-delete-snatch');
        $this->user->toggleAttr('no-pm-delete-snatch', true);
        $this->assertFalse($this->user->notifyDeleteSnatch(), 'uattr-no-pm-delete-snatch');
    }

    public function testPassword(): void {
        $userMan = new \Gazelle\Manager\User;
        $password = randomString(30);
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $this->assertTrue($this->user->updatePassword($password, '0.0.0.0'), 'utest-password-modify');
        $this->assertTrue($this->user->validatePassword($password), 'utest-password-validate-new');
        $this->assertCount(1, $this->user->passwordHistory(), 'utest-password-history');
        $this->assertEquals($this->user->passwordCount(), 1, 'utest-password-count');
    }

    public function testUser(): void {
        $userMan = new \Gazelle\Manager\User;
        $this->assertEquals($this->user->username(), $this->user->flush()->username(), 'utest-flush-username');

        $this->assertEquals($this->user->primaryClass(), USER, 'utest-primary-class');
        $this->assertEquals($this->user->inboxUnreadCount(), 0, 'utest-inbox-unread');
        $this->assertEquals($this->user->allowedPersonalCollages(), 0, 'utest-personal-collages-allowed');
        $this->assertEquals($this->user->paidPersonalCollages(), 0, 'utest-personal-collages-paid');
        $this->assertEquals($this->user->activePersonalCollages(), 0, 'utest-personal-collages-active');
        $this->assertEquals($this->user->collagesCreated(), 0, 'utest-collage-created');
        $this->assertEquals($this->user->pendingInviteCount(), 0, 'utest-personal-collages-active');
        $this->assertEquals($this->user->seedingSize(), 0, 'utest-personal-collages-active');

        $this->assertTrue($this->user->isVisible(), 'utest-is-visble');
        $this->assertTrue($this->user->canLeech(), 'can-leech');
        $this->assertTrue($this->user->permitted('site_upload'), 'utest-permitted-site-upload');
        $this->assertTrue($this->user->permittedAny('site_upload', 'site_debug'), 'utest-permittedAny-site-upload-site-debug');

        $this->assertFalse($this->user->isDisabled(), 'utest-is-disabled');
        $this->assertFalse($this->user->isFLS(), 'utest-is-fls');
        $this->assertFalse($this->user->isInterviewer(), 'utest-is-interviewer');
        $this->assertFalse($this->user->isRecruiter(), 'utest-is-recruiter');
        $this->assertFalse($this->user->isStaff(), 'utest-is-staff');
        $this->assertFalse($this->user->isStaffPMReader(), 'utest-is-staff-pm-reader');
        $this->assertFalse($this->user->isWarned(), 'utest-is-warned');
        $this->assertFalse($this->user->canCreatePersonalCollage(), 'utest-personal-collage-create');
        $this->assertFalse($this->user->permitted('site_debug'), 'utest-permitted-site-debug');

        $this->assertNull($this->user->warningExpiry(), 'utest-warning-expiry');

        $this->assertCount(0, $this->user->announceKeyHistory(), 'utest-announce-key-history');

        // TODO: this will become null
        $this->assertEquals('', $this->user->slogan(), 'utest-slogan');
        $this->assertTrue($this->user->setUpdate('slogan', 'phpunit slogan')->modify(), 'utest-modify-slogan');
    }

    public function testAvatar(): void {
        $userMan = new \Gazelle\Manager\User;
        $this->assertEquals('', $this->user->avatar(), 'utest-avatar-blank');
        $this->assertEquals(
            [
                'image' => USER_DEFAULT_AVATAR,
                'hover' => false,
                'text'  => false,
            ],
            $this->user->avatarComponentList($this->user),
            'utest-avatar-default'
        );

        // defeat the avatar cache
        $this->assertEquals(1, $this->modifyAvatarRender(AvatarDisplay::none, AvatarSynthetic::robot1), 'utest-avatar-update-none');
        $this->assertEquals(AvatarDisplay::none, $this->user->avatarMode(), 'utest-has-avatar-none');
        $new = $userMan->findById($this->user->id());
        $this->assertEquals(USER_DEFAULT_AVATAR, $new->avatarComponentList($this->user->flush())['image'], 'utest-avatar-none');

        $this->assertEquals(1, Helper::modifyUserAvatar($this->user, 'https://www.example.com/avatar.jpg'), 'utest-avatar-set');
        $this->assertEquals('https://www.example.com/avatar.jpg', $this->user->avatar(), 'utest-avatar-url');
        $new = $userMan->findById($this->user->id());
        $this->assertEquals(USER_DEFAULT_AVATAR, $new->avatarComponentList($this->user->flush())['image'], 'utest-avatar-override-none');

        $this->assertEquals(1, $this->modifyAvatarRender(AvatarDisplay::forceSynthetic, AvatarSynthetic::identicon), 'utest-avatar-update-synthetic-identicon');
        $this->assertEquals(AvatarDisplay::forceSynthetic, $this->user->flush()->avatarMode(), 'utest-clone-avatar-forceSynthetic');

        $this->assertEquals(1, $this->modifyAvatarRender(AvatarDisplay::show, AvatarSynthetic::robot1), 'utest-avatar-update-show');
        $new = $userMan->findById($this->user->id());
        $this->assertEquals('https://www.example.com/avatar.jpg', $new->avatarComponentList($this->user->flush())['image'], 'utest-avatar-show');
    }

    public function testLock(): void {
        $this->assertFalse($this->user->isLocked(), 'utest-is-not-locked');
        $this->assertTrue($this->user->setUpdate('lock-type', STAFF_LOCKED)->modify(), 'utest-set-locked');
        $this->user->flush();
        $this->assertTrue($this->user->isLocked(), 'utest-is-now-locked');
        $this->assertEquals(STAFF_LOCKED, $this->user->lockType(), 'utest-lock-type');
        $this->assertTrue($this->user->setUpdate('lock-type', 0)->modify(), 'utest-set-unlocked');
        $this->user->flush();
        $this->assertFalse($this->user->isLocked(), 'utest-is-unlocked');
    }

    public function testStylesheet(): void {
        $manager = new \Gazelle\Manager\Stylesheet;
        $list = $manager->list();
        $this->assertGreaterThan(5, $list, 'we-can-haz-stylesheets');
        $this->assertEquals(count($list), count($manager->usageList('name', 'ASC')), 'stylesheet-list-usage');

        $first = current($list);
        $url   = SITE_URL . 'static/bogus.css';
        $stylesheet = new \Gazelle\User\Stylesheet($this->user);
        $this->assertEquals($this->user->link(), $stylesheet->link(), 'stylesheet-link');
        $this->assertNull($stylesheet->styleUrl(), 'stylesheet-no-external-url');
        $this->assertEquals(1, $stylesheet->modifyInfo($first['id'], null), 'stylesheet-modify');
        $this->assertEquals($first['css_name'], $stylesheet->cssName(), 'stylesheet-css-name');
        $this->assertStringStartsWith("static/styles/{$first['css_name']}/style.css?v=", $stylesheet->cssUrl(), 'stylesheet-css-url');
        $this->assertEquals("static/styles/{$first['css_name']}/images/", $stylesheet->imagePath(), 'stylesheet-image-path');
        $this->assertEquals($first['name'], $stylesheet->name(), 'stylesheet-name');
        $this->assertEquals($first['id'], $stylesheet->styleId(), 'stylesheet-style-id');
        $this->assertEquals($first['theme'], $stylesheet->theme(), 'stylesheet-theme');
        $this->assertEquals(1, $stylesheet->modifyInfo($first['id'], $url), 'stylesheet-set-external');
        $this->assertEquals('External CSS', $stylesheet->name(), 'stylesheet-name');
        $this->assertEquals($url, $stylesheet->styleUrl(), 'stylesheet-external-url');
        $this->assertEquals($url, $stylesheet->cssUrl(), 'stylesheet-external-ccs-url');
    }

    public function testWarning(): void {
        $warned = new \Gazelle\User\Warning($this->user);
        $this->assertFalse($warned->isWarned(), 'utest-warn-initial');
        $end = $warned->create(reason: 'phpunit 1', interval: '1 hour', warner: $this->user);
        $this->assertStringStartsWith(date('Y-m-d '), $end, 'utest-warn-1-hour');
        $this->assertTrue($warned->isWarned(), 'utest-is-warned');
        $this->assertEquals(1, $warned->total(), 'utest-warn-total');

        $userMan = new \Gazelle\Manager\User;
        $this->assertEquals(1, $userMan->warn($this->user, 2, "phpunit warning", $this->user), 'utest-warn-uman');
        $this->assertEquals(2, $warned->total(), 'utest-warn-total');
        $warningList = $warned->warningList();
        $this->assertCount(2, $warningList, 'utest-warn-list');
        $this->assertEquals('phpunit 1', $warningList[0]['reason'], 'utest-warn-first-reason');
        $this->assertEquals('true', $warningList[1]['active'], 'utest-warn-second-active');

        $this->assertEquals(2, $warned->clear(), 'utest-warn-clear');
        $this->assertFalse($warned->isWarned(), 'utest-warn-final');
    }
}
