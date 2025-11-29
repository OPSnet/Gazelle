<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\AvatarDisplay;
use Gazelle\Enum\AvatarSynthetic;
use Gazelle\Enum\UserStatus;

class UserTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(6), 'user');
    }

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function modifyAvatarRender(AvatarDisplay $display, AvatarSynthetic $synthetic): bool {
        return $this->user->setField('option_list', [
                'DisableAvatars' => $display->value,
                'Identicons'     => $synthetic->value
            ])->modify();
    }

    public function testUserFind(): void {
        $userMan = new Manager\User();
        $admin = $userMan->find('@' . $this->user->username());
        $this->assertInstanceOf(User::class, $admin, 'user-find-@');
        $this->user->setField('PermissionID', SYSOP)->modify();
        $this->assertTrue($admin->isStaff(), 'admin-is-admin');
        $this->assertTrue($admin->permitted('site_upload'), 'admin-permitted-site_upload');
        $this->assertFalse($admin->permitted('no_such_privilege'), 'admin-not-permitted-bogus');
        $this->assertTrue($admin->permitted('site_debug'), 'admin-permitted-site_debug');
        $this->assertTrue($admin->permittedAny('site_analysis', 'site_debug'), 'admin-permitted-any-site_analysis-site_debug');
    }

    public function testFindById(): void {
        $userMan = new Manager\User();
        $user = $userMan->findById($this->user->id);
        $this->assertInstanceOf(User::class, $user, 'user-find-by-id');
        $this->assertFalse($user->isStaff(), 'user-is-not-admin');
        $this->assertStringStartsWith('user.', $user->username(), 'user-username');
        $this->assertStringEndsWith('@user.example.com', $user->email(), 'user-email');
        $this->assertFalse($user->isEnabled(), 'user-is-enabled');
        $this->assertTrue($user->isUnconfirmed(), 'user-is-confirmed');
        $this->assertFalse($user->permittedAny('site_analysis', 'site_debug'), 'utest-permittedAny-site-analysis-site-debug');

        $id       = $this->user->id;
        $username = $this->user->username();
        $this->assertEquals(1, $this->user->remove(), 'user-remove-exists');
        $this->assertNull(new Manager\User()->findById($id), 'user-is-removed');
        $this->assertNull(new Manager\User()->findByUsername($username), 'username-is-removed');
        unset($this->user);
    }

    public function testAttr(): void {
        $this->assertFalse($this->user->hasAttr('unlimited-download'), 'uattr-hasUnlimitedDownload');
        $this->user->toggleAttr('unlimited-download', true);
        $this->assertTrue($this->user->hasAttr('unlimited-download'), 'uattr-not-hasUnlimitedDownload');

        $this->assertNull($this->user->option('nosuchoption'), 'uattr-nosuchoption');

        $this->assertEquals(AvatarDisplay::show, $this->user->avatarMode(), 'uattr-avatarMode');
        $this->assertTrue($this->user->showAvatars(), 'uattr-show-avatars');
        $this->assertEquals(0, $this->user->bonusPointsTotal(), 'uattr-bp');
        $this->assertEquals(0, $this->user->downloadedSize(), 'uattr-starting-download');
        $this->assertEquals(POSTS_PER_PAGE, $this->user->postsPerPage(), 'uattr-ppp');
        $this->assertEquals(STARTING_UPLOAD, $this->user->uploadedSize(), 'uattr-starting-upload');
        $this->assertEquals('User', $this->user->userclassName(), 'uattr-userclass-name');

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

    public function testCustomPrivilege(): void {
        $privilege = 'site_unlimit_ajax';
        $this->assertFalse($this->user->permitted($privilege), 'user-permitted-custom-no');
        $this->assertTrue($this->user->addCustomPrivilege($privilege), 'user-permitted-custom-modify');
        $this->assertTrue($this->user->permitted($privilege), 'user-permitted-custom-yes');
        $this->assertEquals(1, $this->user->modifyPrivilegeList([]), 'user-permitted-custom-none');
        $this->assertFalse($this->user->permitted($privilege), 'user-permitted-custom-removed');
    }

    public function testExternalProfile(): void {
        $externalProfile = new User\ExternalProfile($this->user);
        $this->assertEquals('', $externalProfile->profile(), 'user-profile-new');
        $this->assertEquals(1, $externalProfile->modifyProfile('phpunit'), 'user-profile-create');
        $this->assertEquals('phpunit', $externalProfile->profile(), 'user-profile-set');

        $this->assertEquals(1, $externalProfile->modifyProfile('phpunit update'), 'user-profile-update');
        $this->assertEquals('phpunit update', $this->user->externalProfile()->profile(), 'user-profile-delegate');

        $this->assertEquals(1, $externalProfile->remove(), 'user-profile-remove');
        $this->assertEquals('', $externalProfile->profile(), 'user-profile-empty');
    }

    public function testPassword(): void {
        $password = randomString(30);
        $this->user->history()->modifyPassword($password, true)->user()->modify();
        $this->assertLessThan(1, $this->user->history()->passwordAge(), 'utest-password-age');
        $this->assertTrue($this->user->validatePassword($password), 'utest-password-validate-new');
        $this->assertCount(1, $this->user->history()->passwordList(), 'utest-password-list');
        $this->assertEquals(1, $this->user->history()->passwordTotal(), 'utest-password-count');
    }

    public function testUserBasic(): void {
        $this->assertEquals($this->user->username(), $this->user->flush()->username(), 'utest-flush-username');

        $this->assertEquals(0, $this->user->allowedPersonalCollages(), 'utest-personal-collages-allowed');
        $this->assertEquals(0, $this->user->paidPersonalCollages(), 'utest-personal-collages-paid');
        $this->assertEquals(0, $this->user->activePersonalCollages(), 'utest-personal-collages-active');
        $this->assertEquals(0, $this->user->collagesCreated(), 'utest-collage-created');
        $this->assertEquals(0, $this->user->collageUnreadCount(), 'utest-collage-unread-count');
        $this->assertEquals(0, $this->user->forumCatchupEpoch(), 'utest-forum-catchup-epoch');
        $this->assertEquals(0, $this->user->invite()->pendingTotal(), 'utest-pending-invite-count');
        $this->assertEquals(0, $this->user->seedingSize(), 'utest-seeding-size');
        $this->assertEquals(0, $this->user->torrentRecentRemoveCount(1), 'utest-torrent-recent-remove-count');
        $this->assertEquals(0, $this->user->trackerIPCount(), 'utest-tracker-ipaddr-total');
        $this->assertEquals('', $this->user->forbiddenForumsList(), 'utest-forbidden-forum-list');
        $this->assertEquals('', $this->user->referral(), 'utest-referral');
        $this->assertEquals('??', $this->user->ipCountryIso(), 'utest-country-iso');
        $this->assertEquals([], $this->user->tagSnatchCounts(), 'utest-tag-snatch-counts');
        $this->assertEquals([], $this->user->tokenList(new Manager\Torrent(), 0, 0), 'utest-token-list');
        $this->assertEquals([], $this->user->navigationList(), 'utest-navigation-list');
        $this->assertEquals(USER, $this->user->primaryClass(), 'utest-primary-class');

        $this->assertTrue($this->user->isVisible(), 'utest-is-visble');
        $this->assertTrue($this->user->canLeech(), 'utest-can-leech');
        $this->assertTrue($this->user->hasAutocomplete('other'), 'utest-has-autocomplete-other');
        $this->assertTrue($this->user->hasAutocomplete('search'), 'utest-has-autocomplete-search');
        $this->assertTrue($this->user->permitted('site_upload'), 'utest-permitted-site-upload');
        $this->assertTrue($this->user->permittedAny('site_upload', 'site_debug'), 'utest-permittedAny-site-upload-site-debug');
        $this->assertTrue($this->user->updateCatchup(), 'utest-update-catchup');

        $this->assertFalse($this->user->isDisabled(), 'utest-is-disabled');
        $this->assertFalse($this->user->isFLS(), 'utest-is-fls');
        $this->assertFalse($this->user->isInterviewer(), 'utest-is-interviewer');
        $this->assertFalse($this->user->isRecruiter(), 'utest-is-recruiter');
        $this->assertFalse($this->user->isStaff(), 'utest-is-staff');
        $this->assertFalse($this->user->isStaffPMReader(), 'utest-is-staff-pm-reader');
        $this->assertFalse($this->user->isWarned(), 'utest-is-not-warned');
        $this->assertFalse($this->user->canCreatePersonalCollage(), 'utest-personal-collage-create');
        $this->assertFalse($this->user->permitted('site_debug'), 'utest-permitted-site-debug');

        $this->assertNull($this->user->warningExpiry(), 'utest-warning-expiry');

        $this->assertEquals([], $this->user->recentUploadList(), 'utest-recent-upload');
        $this->assertTrue($this->user->flushRecentUpload(), 'utest-flush-recent-upload');

        $this->assertEquals(0, $this->user->tokenCount(), 'utest-token-count');
        $this->assertTrue($this->user->updateTokens(10), 'utest-token-update-10');
        $this->assertTrue($this->user->updateTokens(5), 'utest-token-update-5');
        $this->assertEquals(5, $this->user->tokenCount(), 'utest-token-new-count');

        // TODO: this will become null
        $this->assertEquals('', $this->user->slogan(), 'utest-slogan');
        $this->assertTrue($this->user->setField('slogan', 'phpunit slogan')->modify(), 'utest-modify-slogan');

        $this->assertEquals('', $this->user->IRCKey(), 'utest-no-irc-key');
        $this->user->setField('IRCkey', 'irckey')->modify();
        $this->assertEquals('irckey', $this->user->IRCKey(), 'utest-irc-key');

        $this->user->addStaffNote('phpunit staff note')->modify();
        $eventList = $this->user->auditTrail()->fullEventList();
        $this->assertEquals(
            'phpunit staff note',
            $eventList[0]['note'],
            'utest-staff-note'
        );

        $this->assertFalse($this->user->setTitle(str_repeat('x', USER_TITLE_LENGTH + 1)), 'utest-title-too-long');
        $this->assertTrue($this->user->setTitle('custom title'), 'utest-set-title');
        $this->user->modify();
        $this->assertEquals('custom title', $this->user->title(), 'utest-title');
        $this->assertTrue($this->user->removeTitle()->modify(), 'utest-remove-title');
        $this->assertEquals('', $this->user->title(), 'utest-null-title');
    }

    public function testUpDown(): void {
        $this->assertEquals(STARTING_UPLOAD, $this->user->uploadedSize(), 'utest-initial-up');
        $this->assertEquals(0, $this->user->downloadedSize(), 'utest-initial-down');
        $this->user->setField('leech_upload', STARTING_UPLOAD * 4)
            ->setField('leech_download', STARTING_UPLOAD * 2)
            ->modify();
        $this->assertEquals(STARTING_UPLOAD * 4, $this->user->uploadedSize(), 'utest-modified-up');
        $this->assertEquals(STARTING_UPLOAD * 2, $this->user->downloadedSize(), 'utest-modified-down');
    }

    public function testAvatar(): void {
        $userMan = new Manager\User();
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
        $this->assertTrue($this->modifyAvatarRender(AvatarDisplay::none, AvatarSynthetic::robot1), 'utest-avatar-update-none');
        $this->assertEquals(AvatarDisplay::none, $this->user->avatarMode(), 'utest-has-avatar-none');
        $new = $userMan->findById($this->user->id);
        $this->assertEquals(
            USER_DEFAULT_AVATAR,
            $new?->avatarComponentList($this->user->flush())['image'],
            'utest-avatar-none'
        );

        $url = 'https://www.example.com/avatar.jpg';
        $this->assertTrue($this->user->setField('avatar', $url)->modify(), 'utest-avatar-set');
        $this->assertEquals($url, $this->user->avatar(), 'utest-avatar-url');
        $new = $userMan->findById($this->user->id);
        $this->assertEquals(
            USER_DEFAULT_AVATAR,
            $new?->avatarComponentList($this->user->flush())['image'],
            'utest-avatar-override-none'
        );
        $this->assertTrue(
            $this->modifyAvatarRender(AvatarDisplay::forceSynthetic,
            AvatarSynthetic::identicon),
            'utest-avatar-update-synthetic-identicon'
        );
        $this->assertEquals(
            AvatarDisplay::forceSynthetic,
            $this->user->flush()->avatarMode(),
            'utest-clone-avatar-forceSynthetic'
        );
        $this->assertTrue(
            $this->modifyAvatarRender(AvatarDisplay::show,
            AvatarSynthetic::robot1),
            'utest-avatar-update-show'
        );
        $new = $userMan->findById($this->user->id);
        $this->assertEquals(
            'https://www.example.com/avatar.jpg',
            $new?->avatarComponentList($this->user->flush())['image'],
            'utest-avatar-show'
        );
    }

    public function testLastAccess(): void {
        $this->assertNull($this->user->lastAccess(), 'utest-no-last-access');
        $this->assertEquals(1, $this->user->refreshLastAccess(), 'utest-refresh-last-access');
        $this->assertTrue(
            Helper::recentDate((string)$this->user->lastAccessRealtime()),
            'utest-realtime-last-access'
        );
        $userMan = new Manager\User();
        $this->assertGreaterThan(0, $userMan->refreshLastAccess(), 'utest-userman-refresh-last-access');
        $this->assertTrue(
            Helper::recentDate((string)$this->user->lastAccess()),
            'utest-has-last-access'
        );
    }

    public function testLock(): void {
        $this->assertFalse($this->user->isLocked(), 'utest-is-not-locked');
        $this->assertTrue($this->user->setField('lock-type', STAFF_LOCKED)->modify(), 'utest-set-locked');
        $this->user->flush();
        $this->assertTrue($this->user->isLocked(), 'utest-is-now-locked');
        $this->assertEquals(STAFF_LOCKED, $this->user->lockType(), 'utest-lock-type');
        $this->assertTrue($this->user->setField('lock-type', 0)->modify(), 'utest-set-unlocked');
        $this->user->flush();
        $this->assertFalse($this->user->isLocked(), 'utest-is-unlocked');
    }

    public function testNextClass(): void {
        $this->assertEquals(Enum\UserStatus::unconfirmed, $this->user->userStatus(), 'utest-user-status-unconfirmed');
        $this->user->setField('Enabled', UserStatus::enabled->value)->modify();
        $this->assertEquals(Enum\UserStatus::enabled, $this->user->userStatus(), 'utest-user-status-enabled');

        $manager = new Manager\User();
        $next = $this->user->nextClass($manager);
        $this->assertIsArray($next, 'user-next-array');
        $this->assertEquals('Member', $next['class'], 'user-next-class-is-member');

        $goal = $next['goal'];
        $this->assertIsArray($goal, 'user-next-requirements-array');
        $this->assertCount(3, $goal, 'user-next-requirements-count');
        $this->assertStringContainsString('100%', $goal['Ratio']['percent'],  'user-next-ratio');
        $this->assertStringContainsString('0%',   $goal['Time']['percent'],   'user-next-time');
        $this->assertStringContainsString('30%',  $goal['Upload']['percent'], 'user-next-upload');

        $this->user->setField('created', date('Y-m-d H:i:s', strtotime('-2 day')))->modify();
        $next = $this->user->nextClass($manager);
        $this->assertIsArray($next, 'user-next-requirements-next-array');
        $this->assertStringContainsString('29%', $next['goal']['Time']['percent'], 'user-next-closer-time');
        $this->user->setField('created', date('Y-m-d H:i:s', strtotime('-7 day')))->modify();
        $next = $this->user->nextClass($manager);
        $this->assertIsArray($next, 'user-next-requirements-next-2-array');
        $this->assertStringContainsString('100%', $next['goal']['Time']['percent'], 'user-next-has-time');

        $this->user->addBounty(7 * 1024 * 1024 * 1024);
        $this->user->stats()->flush();
        $next = $this->user->nextClass($manager);
        $this->assertIsArray($next, 'user-next-requirements-next-3-array');
        $this->assertStringContainsString('100%',  $next['goal']['Upload']['percent'], 'user-next-has-upload');

        $manager->promote();
        $this->assertEquals('Member', $this->user->flush()->userclassName(), 'user-promoted-to-member');

        $this->user->setField('PermissionId', SYSOP)->modify();
        $this->assertNull($this->user->nextClass($manager), 'user-next-class-is-null');
    }

    public function testStylesheet(): void {
        global $Cache;
        $Cache->delete_value(Manager\Stylesheet::CACHE_KEY);
        $manager = new Manager\Stylesheet();
        $list = $manager->list();
        $this->assertGreaterThan(5, $list, 'we-can-haz-stylesheets');
        $this->assertEquals(count($list), count($manager->usageList()), 'stylesheet-list-usage');

        $sheet = end($list);
        $url   = STATIC_SERVER . '/bogus.css';
        $stylesheet = $this->user->stylesheet();
        $this->assertNull($stylesheet->styleUrl(), 'stylesheet-no-external-url');
        $this->assertEquals(1, $stylesheet->modifyInfo($sheet['id'], null), 'stylesheet-modify');
        $this->assertEquals($sheet['css_name'], $stylesheet->cssName(), 'stylesheet-css-name');
        $this->assertStringContainsString("/static/styles/{$sheet['css_name']}/style.css?v=", $stylesheet->cssUrl(), 'stylesheet-css-url');
        $this->assertStringContainsString("/static/styles/{$sheet['css_name']}/images/", $stylesheet->imagePath(), 'stylesheet-image-path');
        $this->assertEquals($sheet['name'], $stylesheet->name(), 'stylesheet-name');
        $this->assertEquals($sheet['id'], $stylesheet->styleId(), 'stylesheet-style-id');
        $this->assertEquals($sheet['theme'], $stylesheet->theme(), 'stylesheet-theme');
        $this->assertEquals(1, $stylesheet->modifyInfo($sheet['id'], $url), 'stylesheet-set-external');
        $this->assertEquals('External CSS', $stylesheet->name(), 'stylesheet-name');
        $this->assertEquals($url, $stylesheet->styleUrl(), 'stylesheet-external-url');
        $this->assertEquals($url, $stylesheet->cssUrl(), 'stylesheet-external-ccs-url');
    }

    public function testWarning(): void {
        $warned = new User\Warning($this->user);
        $this->assertFalse($warned->isWarned(), 'utest-warn-initial');
        $end = $warned->add(reason: 'phpunit 1', interval: '1 hour', warner: $this->user);
        $this->assertTrue(Helper::recentDate($end, -60 * 60 + 60), 'utest-warn-1-hour'); // one hour - 60 seconds
        $this->assertTrue($warned->isWarned(), 'utest-is-warned');
        $this->assertEquals(1, $warned->total(), 'utest-warn-total');

        $end = $this->user->warn(2, "phpunit warning", $this->user, "phpunit");
        $this->assertTrue(Helper::recentDate($end, strtotime('+2 weeks') - 60), 'utest-warn-in-future'); // two weeks - 60 seconds
        $this->assertEquals(2, $warned->total(), 'utest-warn-total');
        $warningList = $warned->warningList();
        $this->assertCount(2, $warningList, 'utest-warn-list');
        $this->assertEquals('phpunit 1', $warningList[0]['reason'], 'utest-warn-first-reason');
        $this->assertTrue($warningList[0]['active'], 'utest-warn-first-active');
        $this->assertFalse($warningList[1]['active'], 'utest-warn-second-inactive');

        $this->assertEquals(2, $warned->clear(), 'utest-warn-clear');
        $this->assertFalse($warned->isWarned(), 'utest-warn-final');
    }

    public function testLogin(): void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.255';
        Base::setRequestContext(new RequestContext(
            'phpunit', $_SERVER['REMOTE_ADDR'], 'whatever'
        ));
        $login  = new Login();
        $ipaddr = $_SERVER['REMOTE_ADDR'];
        $watch  = new LoginWatch($ipaddr);
        $this->assertEquals(0, $watch->nrAttempts(), 'loginwatch-init-attempt');
        $this->assertEquals(0, $watch->nrBans(), 'loginwatch-init-ban');

        $result = $login->login(
            username: 'email@example.com',
            password: 'password',
            watch:    $watch,
        );
        $this->assertNull($result, 'login-bad-username');
        $this->assertEquals($login->error(), Enum\LoginStatus::badCredentials, 'login-error-username');
        $this->assertEquals('email@example.com', $login->username(), 'login-username');
        $this->assertEquals($ipaddr, $login->requestContext()->remoteAddr(), 'login-ipaddr');
        $this->assertFalse($login->persistent(), 'login-persistent');
        $this->assertEquals(1, $watch->nrAttempts(), 'loginwatch-attempt');

        $result = $login->login(
            username: $this->user->username(),
            password: 'password',
            watch:    $watch,
        );
        $this->assertNull($result, 'login-bad-password');
        $this->assertEquals($login->error(), Enum\LoginStatus::badCredentials, 'login-error-password');
        $this->assertEquals(2, $watch->nrAttempts(), 'loginwatch-more-attempt');

        $this->assertGreaterThan(0, count($watch->activeList(10, 0)), 'loginwatch-active-list');
        $this->assertGreaterThan(0, $watch->clearAttempts(), 'loginwatch-clear');
        $this->assertEquals(0, $watch->nrAttempts(), 'loginwatch-no-attempts');

        $banned = $watch->setBan('phpunit ban', [$watch->id()], $this->user);
        $this->assertEquals(1, $banned, 'loginwatch-ip-ban');

        $banMan = new Manager\Ban();
        $ban = $banMan->findByIp($login->requestContext()->remoteAddr());
        $this->assertInstanceOf(Ban::class, $ban, 'loginwatch-find-ban');
        $this->assertTrue(
            $banMan->isBanned($login->requestContext()->remoteAddr()),
            'loginwatch-ip-is-banned'
        );
        $banMan->setFilterIpaddr($login->requestContext()->remoteAddr());
        $this->assertEquals(1, $banMan->total(), 'loginwatch-ip-total');
        $page = $banMan->page(2, 0);
        $this->assertCount(1, $page, 'loginwatch-ip-page');
        $this->assertEquals($ban->id, $page[0]['id'], 'loginwatch-ip-id-page');
        $this->assertEquals(1, $ban->remove(), 'loginwatch-ip-ban-clear');

        $this->assertEquals(3, $this->user->history()->resetIp(), 'user-reset-ip');
    }

    public function testParanoia(): void {
        $this->assertEquals([], $this->user->paranoia(), 'utest-paranoia');
        $this->assertEquals('Off', $this->user->paranoiaLabel(), 'utest-paranoid-label-off');
        $this->assertEquals(0, $this->user->paranoiaLevel(), 'utest-paranoid-level-off');
        $this->assertFalse($this->user->isParanoid('lastseen'), 'utest-is-not-last-seen-paranoid');
    }

    public function testAnnounceKey(): void {
        $key = $this->user->announceKey();
        $url = $this->user->announceUrl();

        $this->assertEquals(0, $this->user->history()->announceKeyTotal(), 'utest-announce-key-count');
        $this->assertEquals(32, strlen($key), 'utest-announce-key');
        $this->assertStringStartsWith(TRACKER_PUBLIC_HOST, $url, 'utest-announce-url-begin');
        $this->assertStringEndsWith('/announce', $url, 'utest-announce-url-end');
        $this->assertCount(0, $this->user->history()->announceKeyList(), 'utest-announce-key-list');

        $new = randomString(32);
        $id  = $this->user->history()->modifyAnnounceKey($key, $new);
        $this->assertGreaterThan(0, $id, 'utest-announce-key-modify');
        $this->user->modify();
        $this->assertEquals($new, $this->user->announceKey(), 'utest-new-announce-key');
        $this->assertEquals(1, $this->user->history()->announceKeyTotal(), 'utest-announce-key-new-count');
        $history = current($this->user->history()->announceKeyList());
        $this->assertCount(4, $history, 'utest-announce-key-new-history');
        $this->assertEquals($key, $history['previous'], 'utest-announce-key-history-old');
        $this->assertEquals(
            $this->user->requestContext()->remoteAddr(),
            $history['ip'],
            'utest-announce-key-history-ipaddr'
        );
        $this->assertTrue(Helper::recentDate($history['created']), 'utest-announce-key-history-date');
    }

    public function testInactive(): void {
        $userMan = new Manager\User();
        $db      = DB::DB();

        $this->user->setField('Enabled', UserStatus::enabled->value)->modify();
        $db->prepared_query("
            INSERT INTO user_last_access (user_id, last_access) VALUES (?, now() - INTERVAL ? DAY)
            ", $this->user->id, INACTIVE_USER_WARN_DAYS + 1
        );
        $this->user->flush();
        $this->assertEquals(1, $userMan->inactiveUserWarn(new Util\Mail()), 'utest-one-user-inactive-warned');
        $this->assertTrue($this->user->hasAttr('inactive-warning-sent'), 'utest-inactive-warned');

        $db->prepared_query("
            UPDATE user_last_access SET last_access = now() - INTERVAL ? DAY WHERE user_id = ?
            ", INACTIVE_USER_DEACTIVATE_DAYS + 1, $this->user->id
        );
        $this->assertEquals(1, $userMan->inactiveUserDeactivate(new Tracker()), 'utest-one-user-inactive-deactivated');
        $this->user->flush();
        $this->assertTrue($this->user->isDisabled(), 'utest-inactive-deactivated');
    }

    public function testLastFM(): void {
        $lastfm = new Util\LastFM();
        $this->assertEquals(
            'name',
            $lastfm->cleanUsername('https://last.fm/user/name'),
            'lastfm-clear-name'
        );
        $username = 'phpunit.' . randomString(6);
        $this->assertEquals($username, $lastfm->cleanUsername($username), 'lastfm-clear-name');
        $this->assertNull($lastfm->username($this->user), 'lastfm-no-username');
        $this->assertEquals(1, $lastfm->modifyUsername($this->user, $username), 'lastfm-create-username');
        $this->assertEquals($username, $lastfm->username($this->user), 'lastfm-has-username');

        $username = "new.$username";
        $this->assertEquals(1, $lastfm->modifyUsername($this->user, $username), 'lastfm-modify-username');
        $this->assertEquals($username, $lastfm->username($this->user), 'lastfm-has-new-username');
        $this->assertEquals(0, $lastfm->modifyUsername($this->user, $username), 'lastfm-no-modify-username');

        $this->assertEquals(1, $lastfm->modifyUsername($this->user, ''), 'lastfm-remove-username');
        $this->assertNull($lastfm->username($this->user), 'lastfm-has-no-username');
    }

    public function testSecondaryClass(): void {
        $this->assertFalse($this->user->isFLS(), 'user-is-not-secondary-fls');
        $this->user->privilege()->addSecondaryClass(FLS_TEAM);
        $this->assertTrue($this->user->isFLS(), 'user-is-secondary-fls');
        $this->user->privilege()->removeSecondaryClass(FLS_TEAM);
        $this->assertFalse($this->user->isFLS(), 'user-is-no-longer-secondary-fls');
    }

    public function testUserRank(): void {
        $rank = new UserRank(
            new UserRank\Configuration(RANKING_WEIGHT),
            [
                'downloaded'     => 0,
                'uploaded'       => STARTING_UPLOAD,
                'uploads'        => 0,
                'requests'       => 0,
                'posts'          => 0,
                'bounty'         => 0,
                'artists'        => 0,
                'collage-add'    => 0,
                'collage-create' => 0,
                'votes'          => 0,
                'bonus'          => 0,
                'comment-t'      => 0,
            ]
        );
        $this->assertEquals(0, $rank->score(), 'userrank-score');
        $this->assertEquals(0, $rank->rank('downloaded'), 'userrank-rank');
    }

    public function testUserHash(): void {
        // ensure the same text hashed by two users is different
        $second = Helper::makeUser('user.' . randomString(6), 'user');
        $this->assertNotEquals(
            $this->user->hashHmac('topic', 'phpunit'),
            $second->hashHmac('topic', 'phpunit'),
            'user-hash-user-distinct'
        );
        $second->remove();
    }

    public function testDebugProfile(): void {
        global $Debug;
        $Debug->flush(); // clear out the accumulated errors
        $case = $Debug->profile($this->user, false);
        $this->assertNull($case, 'user-debug-profile-no-reason');
        $case = $Debug->profile($this->user, true);
        $this->assertInstanceOf(ErrorLog::class, $case, 'user-debug-profile-a-reason');
        $case->remove();
    }
}
