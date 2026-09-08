<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\AvatarDisplay;
use Gazelle\Enum\AvatarSynthetic;
use Gazelle\Enum\UserStatus;

class UserTest extends TestCase {
    use Pg;

    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(6), 'user');
    }

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->remove();
        }
        $this->pg()->query('
            delete from login_watch where ip = $1
            ', Helper::IP_ADDR
        );
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
        $this->assertSame(1, $this->user->remove(), 'user-remove-exists');
        $this->assertNull(new Manager\User()->findById($id), 'user-is-removed');
        $this->assertNull(new Manager\User()->findByUsername($username), 'username-is-removed');
        unset($this->user);
    }

    public function testUserAttr(): void {
        $this->assertFalse($this->user->hasAttr('unlimited-download'), 'uattr-hasUnlimitedDownload');
        $this->user->toggleAttr('unlimited-download', true);
        $this->assertTrue($this->user->hasAttr('unlimited-download'), 'uattr-not-hasUnlimitedDownload');

        $this->assertSame(AvatarDisplay::show, $this->user->avatarMode(), 'uattr-avatarMode');
        $this->assertTrue($this->user->showAvatars(), 'uattr-show-avatars');
        $this->assertSame(0, $this->user->bonusPointsTotal(), 'uattr-bp');
        $this->assertSame(0, $this->user->downloadedSize(), 'uattr-starting-download');
        $this->assertSame(POSTS_PER_PAGE, $this->user->postsPerPage(), 'uattr-ppp');
        $this->assertSame(STARTING_UPLOAD, $this->user->uploadedSize(), 'uattr-starting-upload');
        $this->assertSame('User', $this->user->userclassName(), 'uattr-userclass-name');

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

    public function testUserOption(): void {
        $this->assertNull($this->user->option('nosuchoption'), 'uopt-nosuchoption');
        $this->assertTrue(
            $this->user->autocomplete() === Enum\Autocomplete::everywhere,
            'uopt-autocomplete-default'
        );
        $this->user->modifyOption('AutoComplete', Enum\Autocomplete::disabled->value);
        $this->assertFalse(
            $this->user->autocomplete() === Enum\Autocomplete::searchOnly,
            'uopt-autocomplete-disabled-search'
        );
        $this->assertFalse(
            $this->user->autocomplete() === Enum\Autocomplete::everywhere,
            'uopt-autocomplete-disabled-other'
        );
        $this->user->modifyOption('AutoComplete', Enum\Autocomplete::searchOnly->value);
        $this->assertTrue(
            $this->user->autocomplete() === Enum\Autocomplete::searchOnly,
            'uopt-autocomplete-searchOnly-search'
        );
        $this->assertFalse(
            $this->user->autocomplete() === Enum\Autocomplete::everywhere, /** @phpstan-ignore-line identical.alwaysFalse overthinking it */
            'uopt-autocomplete-searchOnly-other'
        );
        $this->user->modifyOption('AutoComplete', null);
        $this->assertTrue(
            $this->user->autocomplete() === Enum\Autocomplete::everywhere,
            'uopt-autocomplete-null-other'
        );
    }

    public function testCustomPrivilege(): void {
        $privilege = 'site_unlimit_ajax';
        $this->assertFalse($this->user->permitted($privilege), 'user-permitted-custom-no');
        $this->assertTrue($this->user->addCustomPrivilege($privilege), 'user-permitted-custom-modify');
        $this->assertTrue($this->user->permitted($privilege), 'user-permitted-custom-yes');
        $this->assertSame(1, $this->user->modifyPrivilegeList([]), 'user-permitted-custom-none');
        $this->assertFalse($this->user->permitted($privilege), 'user-permitted-custom-removed');
    }

    public function testExternalProfile(): void {
        $externalProfile = new User\ExternalProfile($this->user);
        $this->assertSame('', $externalProfile->profile(), 'user-profile-new');
        $this->assertSame(1, $externalProfile->modifyProfile('phpunit'), 'user-profile-create');
        $this->assertSame('phpunit', $externalProfile->profile(), 'user-profile-set');

        $this->assertSame(1, $externalProfile->modifyProfile('phpunit update'), 'user-profile-update');
        $this->assertSame('phpunit update', $this->user->externalProfile()->profile(), 'user-profile-delegate');

        $this->assertSame(1, $externalProfile->remove(), 'user-profile-remove');
        $this->assertSame('', $externalProfile->profile(), 'user-profile-empty');
    }

    public function testPassword(): void {
        $password = randomString(30);
        $this->user->history()->modifyPassword($password, true)->user()->modify();
        $this->assertLessThan(1, $this->user->history()->passwordAge(), 'utest-password-age');
        $this->assertTrue($this->user->validatePassword($password), 'utest-password-validate-new');
        $this->assertCount(1, $this->user->history()->passwordList(), 'utest-password-list');
        $this->assertSame(1, $this->user->history()->passwordTotal(), 'utest-password-count');
    }

    public function testUserBasic(): void {
        $this->assertSame($this->user->username(), $this->user->flush()->username(), 'utest-flush-username');

        $this->assertSame(0, $this->user->allowedPersonalCollages(), 'utest-personal-collages-allowed');
        $this->assertSame(0, $this->user->paidPersonalCollages(), 'utest-personal-collages-paid');
        $this->assertSame(0, $this->user->activePersonalCollages(), 'utest-personal-collages-active');
        $this->assertSame(0, $this->user->collagesCreated(), 'utest-collage-created');
        $this->assertSame(0, $this->user->collageUnreadCount(), 'utest-collage-unread-count');
        $this->assertSame(0, $this->user->forumCatchupEpoch(), 'utest-forum-catchup-epoch');
        $this->assertSame(0, $this->user->invite()->pendingTotal(), 'utest-pending-invite-count');
        $this->assertSame(0, $this->user->seedingSize(), 'utest-seeding-size');
        $this->assertSame(0, $this->user->torrentRecentRemoveCount(1), 'utest-torrent-recent-remove-count');
        $this->assertSame(0, $this->user->trackerIPCount(), 'utest-tracker-ipaddr-total');
        $this->assertSame('', $this->user->referral(), 'utest-referral');
        $this->assertSame('XX', $this->user->ipCountryIso(), 'utest-country-iso');
        $this->assertSame('127.0.0.1', $this->user->ipaddr(), 'utest-country-ipaddr');
        $this->assertSame([], $this->user->tagSnatchCounts(), 'utest-tag-snatch-counts');
        $this->assertSame([], $this->user->tokenList(new Manager\Torrent(), 0, 0), 'utest-token-list');
        $this->assertSame([], $this->user->navigationList(), 'utest-navigation-list');
        $this->assertSame(USER, $this->user->primaryClass(), 'utest-primary-class');

        $this->assertTrue($this->user->isVisible(), 'utest-is-visble');
        $this->assertTrue($this->user->canLeech(), 'utest-can-leech');
        $this->assertTrue($this->user->permitted('site_upload'), 'utest-permitted-site-upload');
        $this->assertTrue($this->user->permittedAny('site_upload', 'site_debug'), 'utest-permittedAny-site-upload-site-debug');
        $this->assertTrue($this->user->updateCatchup(), 'utest-update-catchup');

        $this->assertFalse($this->user->isDisabled(), 'utest-is-disabled');
        $this->assertFalse($this->user->isFLS(), 'utest-is-fls');
        $this->assertFalse($this->user->isInterviewer(), 'utest-is-interviewer');
        $this->assertFalse($this->user->isRecruiter(), 'utest-is-recruiter');
        $this->assertFalse($this->user->isStaff(), 'utest-is-staff');
        $this->assertFalse($this->user->isStaffPMReader(), 'utest-is-staff-pm-reader');
        $this->assertFalse($this->user->warning()->isWarned(), 'utest-is-not-warned');
        $this->assertFalse($this->user->canCreatePersonalCollage(), 'utest-personal-collage-create');
        $this->assertFalse($this->user->permitted('site_debug'), 'utest-permitted-site-debug');

        $this->assertNull($this->user->warning()->expiry(), 'utest-warning-expiry');

        $this->assertSame([], $this->user->recentUploadList(), 'utest-recent-upload');
        $this->assertTrue($this->user->flushRecentUpload(), 'utest-flush-recent-upload');

        $this->assertSame(0, $this->user->tokenCount(), 'utest-token-count');
        $this->assertTrue($this->user->updateTokens(10), 'utest-token-update-10');
        $this->assertTrue($this->user->updateTokens(5), 'utest-token-update-5');
        $this->assertSame(5, $this->user->tokenCount(), 'utest-token-new-count');

        $this->assertNull($this->user->slogan(), 'utest-slogan');
        $this->assertTrue($this->user->setField('slogan', 'phpunit slogan')->modify(), 'utest-modify-slogan');

        $this->assertNull($this->user->IRCKey(), 'utest-no-irc-key');
        $this->user->setField('IRCkey', 'irckey')->modify();
        $this->assertSame('irckey', $this->user->IRCKey(), 'utest-irc-key');

        $this->user->addStaffNote('phpunit staff note')->modify();
        $eventList = $this->user->auditTrail()->fullEventList();
        $this->assertSame(
            'phpunit staff note',
            $eventList[0]['note'],
            'utest-staff-note'
        );
    }

    public function testUserProfile(): void {
        $this->assertSame('Profile', $this->user->profileTitle(), 'utest-profile-initial');
        $profile = randomString();
        $this->user->setField('profile_title', $profile)->modify();
        $this->assertSame($profile, $this->user->profileTitle(), 'utest-profile-changed');
    }

    public function testUserRequestDefaults(): void {
        $this->assertSame(
            REQUEST_MIN * 1024 ** 2,
            $this->user->requestCreationValue(),
            'utest-req-create-value-default',
        );
        $createValue = 10 * 1024 ** 3;
        $this->user->ordinal()->set('request-bounty-create', $createValue);
        $this->assertSame(
            $createValue,
            $this->user->requestCreationValue(),
            'utest-req-create-value-custom',
        );
        $this->assertSame(
            [
                'value' => '10.00',
                'unit'  => 'GiB'
            ],
            $this->user->requestCreationInfo(),
            'utest-req-create-info-custom',
        );
        $this->assertSame(
            REQUEST_MIN * 1024 ** 2,
            $this->user->requestVoteValue(),
            'utest-req-vote-value-default',
        );
        $voteValue = 200 * 1024 ** 2;
        $this->user->ordinal()->set('request-bounty-vote', $voteValue);
        $this->assertSame(
            $voteValue,
            $this->user->requestVoteValue(),
            'utest-req-vote-custom',
        );
        $this->assertSame(
            [
                'value' => '200.00',
                'unit'  => 'MiB'
            ],
            $this->user->requestVoteInfo(),
            'utest-req-vote-info-custom',
        );
    }

    public function testUpDown(): void {
        $this->assertSame(STARTING_UPLOAD, $this->user->uploadedSize(), 'utest-initial-up');
        $this->assertSame(0, $this->user->downloadedSize(), 'utest-initial-down');
        $this->user->setField('leech_upload', STARTING_UPLOAD * 4)
            ->setField('leech_download', STARTING_UPLOAD * 2)
            ->modify();
        $this->assertSame(STARTING_UPLOAD * 4, $this->user->uploadedSize(), 'utest-modified-up');
        $this->assertSame(STARTING_UPLOAD * 2, $this->user->downloadedSize(), 'utest-modified-down');
    }

    public function testAvatar(): void {
        $userMan = new Manager\User();
        $this->assertSame('', $this->user->avatar(), 'utest-avatar-blank');
        $this->assertSame(
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
        $this->assertSame(AvatarDisplay::none, $this->user->avatarMode(), 'utest-has-avatar-none');
        $new = $userMan->findById($this->user->id);
        $this->assertSame(
            USER_DEFAULT_AVATAR,
            $new?->avatarComponentList($this->user->flush())['image'],
            'utest-avatar-none'
        );

        $url = 'https://www.example.com/avatar.jpg';
        $this->assertTrue($this->user->setField('avatar', $url)->modify(), 'utest-avatar-set');
        $this->assertSame($url, $this->user->avatar(), 'utest-avatar-url');
        $new = $userMan->findById($this->user->id);
        $this->assertSame(
            USER_DEFAULT_AVATAR,
            $new?->avatarComponentList($this->user->flush())['image'],
            'utest-avatar-override-none'
        );
        $this->assertTrue(
            $this->modifyAvatarRender(AvatarDisplay::forceSynthetic,
            AvatarSynthetic::identicon),
            'utest-avatar-update-synthetic-identicon'
        );
        $this->assertSame(
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
        $this->assertSame(
            'https://www.example.com/avatar.jpg',
            $new?->avatarComponentList($this->user->flush())['image'],
            'utest-avatar-show'
        );
    }

    public function testLastAccess(): void {
        $this->assertNull($this->user->lastAccess(), 'utest-no-last-access');
        $this->assertSame(1, $this->user->refreshLastAccess(), 'utest-refresh-last-access');
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
        $this->assertSame(STAFF_LOCKED, $this->user->lockType(), 'utest-lock-type');
        $this->assertTrue($this->user->setField('lock-type', 0)->modify(), 'utest-set-unlocked');
        $this->user->flush();
        $this->assertFalse($this->user->isLocked(), 'utest-is-unlocked');
    }

    public function testStylesheet(): void {
        global $Cache;
        $Cache->delete_value(Manager\Stylesheet::CACHE_KEY);
        $manager = new Manager\Stylesheet();
        $list = $manager->list();
        $this->assertGreaterThan(5, $list, 'we-can-haz-stylesheets');
        $this->assertCount(
            count($list),
            $manager->usageList(),
            'stylesheet-list-usage',
        );

        $sheet = array_last($list);
        $url   = STATIC_SERVER . '/bogus.css';
        $stylesheet = $this->user->stylesheet();
        $this->assertNull($stylesheet->styleUrl(), 'stylesheet-no-external-url');
        $this->assertSame(1, $stylesheet->modifyInfo($sheet['id'], null), 'stylesheet-modify');
        $this->assertSame($sheet['css_name'], $stylesheet->cssName(), 'stylesheet-css-name');
        $this->assertStringContainsString("/static/styles/{$sheet['css_name']}/style.css?v=", $stylesheet->cssUrl(), 'stylesheet-css-url');
        $this->assertStringContainsString("/static/styles/{$sheet['css_name']}/images/", $stylesheet->imagePath(), 'stylesheet-image-path');
        $this->assertSame($sheet['name'], $stylesheet->name(), 'stylesheet-name');
        $this->assertSame($sheet['id'], $stylesheet->styleId(), 'stylesheet-style-id');
        $this->assertSame($sheet['theme'], $stylesheet->theme(), 'stylesheet-theme');
        $this->assertSame(1, $stylesheet->modifyInfo($sheet['id'], $url), 'stylesheet-set-external');
        $this->assertSame('External CSS', $stylesheet->name(), 'stylesheet-name');
        $this->assertSame($url, $stylesheet->styleUrl(), 'stylesheet-external-url');
        $this->assertEquals($url, $stylesheet->cssUrl(), 'stylesheet-external-ccs-url');
    }

    public function testWarning(): void {
        $warned = $this->user->warning();
        $this->assertFalse($warned->isWarned(), 'utest-warn-initial');
        $end = $warned->add(staffReason: 'phpunit 1', interval: '1 hour', warner: $this->user);
        $this->assertTrue(
            // one hour - 60 seconds
            Helper::recentDateTimeImmutable($end->upper, -3600 + 60),
            'utest-warn-1-hour',
        );
        $this->assertTrue($warned->isWarned(), 'utest-is-warned');
        $this->assertSame(1, $warned->total(), 'utest-warn-total');

        $end = $warned->warn(2, "phpunit warning", $this->user, "phpunit");
        $this->assertTrue(
            // two weeks - 60 seconds
            Helper::recentDateTimeImmutable($end->upper, strtotime('+2 weeks') - 60),
            'utest-warn-in-future',
        );
        $this->assertSame(2, $warned->total(), 'utest-warn-total');
        $warningList = $warned->warningList();
        $this->assertCount(2, $warningList, 'utest-warn-list');
        $this->assertSame('phpunit 1', $warningList[0]['reason'], 'utest-warn-first-reason');
        $this->assertTrue($warningList[0]['active'], 'utest-warn-first-active');
        $this->assertFalse($warningList[1]['active'], 'utest-warn-second-inactive');

        $this->assertSame(2, $warned->clear(), 'utest-warn-clear');
        $this->assertFalse($warned->isWarned(), 'utest-warn-final');
    }

    public function testLoginBasic(): void {
        $_SERVER['REMOTE_ADDR'] = Helper::IP_ADDR;
        Base::setRequestContext(new RequestContext(
            'phpunit', $_SERVER['REMOTE_ADDR'], 'whatever'
        ));
        $login  = new Login();
        $ipaddr = $_SERVER['REMOTE_ADDR'];
        $watch  = new LoginWatch($ipaddr);
        $this->assertSame(0, $watch->attemptTotal(), 'loginwatch-init-attempt');
        $this->assertSame(0, $watch->blockTotal(), 'loginwatch-init-block');

        $result = $login->login(
            username: 'email@example.com',
            password: 'password',
            watch:    $watch,
        );
        $this->assertNull($result, 'login-bad-username');
        $this->assertSame($login->error(), Enum\LoginStatus::badCredentials, 'login-error-username');
        $this->assertSame('email@example.com', $login->username(), 'login-username');
        $this->assertSame($ipaddr, $login->requestContext()->remoteAddr(), 'login-ipaddr');
        $this->assertFalse($login->persistent(), 'login-persistent');
        $this->assertSame(1, $watch->attemptTotal(), 'loginwatch-attempt');

        $result = $login->login(
            username: $this->user->username(),
            password: 'password',
            watch:    $watch,
        );
        $this->assertNull($result, 'login-bad-password');
        $this->assertSame($login->error(), Enum\LoginStatus::badCredentials, 'login-error-password');
        $this->assertSame(2, $watch->attemptTotal(), 'loginwatch-more-attempt');

        $watchMan = new Manager\LoginWatch();
        $total = $watchMan->activeTotal();
        $this->assertGreaterThanOrEqual(0, $total, 'user-login-watch-total');
        $this->assertCount(
            $total,
            $watchMan->activeList(10, 0),
            'loginwatch-active-list'
        );
        $this->assertSame(1, $watch->clearAttempt(), 'loginwatch-clear');
        $this->assertSame(0, $watch->attemptTotal(), 'loginwatch-no-attempts');

        $banMan = new Manager\Ban();
        $banned = $banMan->create($watch->ipaddr, 'phpunit ban', $this->user);
        $this->assertSame($watch->ipaddr, $banned->ip(), 'loginwatch-ip-ban');

        $ban = $banMan->findByIp($login->requestContext()->remoteAddr());
        $this->assertInstanceOf(Ban::class, $ban, 'loginwatch-find-ban');
        $this->assertTrue(
            $banMan->isBanned($login->requestContext()->remoteAddr()),
            'loginwatch-ip-is-banned'
        );
        $banMan->setFilterIpaddr($login->requestContext()->remoteAddr());
        $this->assertSame(1, $banMan->total(), 'loginwatch-ip-total');
        $page = $banMan->page(2, 0);
        $this->assertCount(1, $page, 'loginwatch-ip-page');
        $this->assertSame($ban->id, $page[0]['id'], 'loginwatch-ip-id-page');
        $this->assertSame(1, $ban->remove(), 'loginwatch-ip-ban-clear');

        $this->assertSame(1, $this->user->history()->resetIp(), 'user-reset-ip');
    }

    public function testLoginTrim(): void {
        /* There is no point capturing usernames that are too long for the site schema */
        $username = '12345678901234567890'; // 20 chars max
        $watch    = new LoginWatch(Helper::IP_ADDR);
        new Login()->login(
            username: $username . 'z',
            password: 'password',
            watch:    $watch,
        );
        $this->assertSame(
            $username,
            $watch->capture(),
            'login-username-trim'
        );
    }

    public function testParanoia(): void {
        $this->assertSame([], $this->user->paranoia(), 'utest-paranoia');
        $this->assertSame('Off', $this->user->paranoiaLabel(), 'utest-paranoid-label-off');
        $this->assertSame(0, $this->user->paranoiaLevel(), 'utest-paranoid-level-off');
        $this->assertFalse($this->user->isParanoid('lastseen'), 'utest-is-not-last-seen-paranoid');
    }

    public function testParanoiaOverride(): void {
        $this->assertSame([], $this->user->paranoia(), 'utest-paranoia-override');
        $this->user->setOverrideParanoia(['lastseen']);
        $this->assertSame(['lastseen'], $this->user->paranoia(), 'utest-paranoia-override-list');
        $this->assertSame('Very Low', $this->user->paranoiaLabel(), 'utest-paranoid-label-custom');
        $this->assertTrue($this->user->isParanoid('lastseen'), 'utest-is-last-seen-paranoid');
    }

    public function testAnnounceKey(): void {
        $key = $this->user->announceKey();
        $url = $this->user->announceUrl();

        $this->assertSame(0, $this->user->history()->announceKeyTotal(), 'utest-announce-key-count');
        $this->assertSame(32, strlen($key), 'utest-announce-key');
        $this->assertStringStartsWith(TRACKER_PUBLIC_HOST, $url, 'utest-announce-url-begin');
        $this->assertStringEndsWith('/announce', $url, 'utest-announce-url-end');
        $this->assertCount(0, $this->user->history()->announceKeyList(), 'utest-announce-key-list');

        $new = randomString(32);
        $id  = $this->user->history()->modifyAnnounceKey($key, $new);
        $this->assertGreaterThan(0, $id, 'utest-announce-key-modify');
        $this->user->modify();
        $this->assertSame($new, $this->user->announceKey(), 'utest-new-announce-key');
        $this->assertSame(1, $this->user->history()->announceKeyTotal(), 'utest-announce-key-new-count');
        $history = array_first($this->user->history()->announceKeyList());
        $this->assertCount(4, $history, 'utest-announce-key-new-history');
        $this->assertSame($key, $history['previous'], 'utest-announce-key-history-old');
        $this->assertSame(
            $this->user->requestContext()->remoteAddr(),
            $history['ip'],
            'utest-announce-key-history-ipaddr'
        );
        $this->assertTrue(
            Helper::recentDateTimeImmutable($history['created']),
            'utest-announce-key-history-date',
        );
    }

    public function testInactive(): void {
        $userMan = new Manager\User();
        $db      = DB::DB();

        $this->assertSame(
            Enum\UserStatus::unconfirmed,
            $this->user->userStatus(),
            'user-status-unconfirmed',
        );
        $this->user->setField('Enabled', Enum\UserStatus::enabled->value)->modify();
        $this->assertSame(
            Enum\UserStatus::enabled,
            $this->user->userStatus(),
            'user-status-enabled',
        );
        $db->prepared_query("
            INSERT INTO user_last_access (user_id, last_access) VALUES (?, now() - INTERVAL ? DAY)
            ", $this->user->id, INACTIVE_USER_WARN_DAYS + 1
        );
        $this->user->flush();
        $this->assertSame(
            1,
            $userMan->inactiveUserWarn(),
            'utest-one-user-inactive-warned'
        );
        $this->assertTrue(
            $this->user->hasAttr('inactive-warning-sent'),
            'utest-inactive-warned',
        );

        $db->prepared_query("
            UPDATE user_last_access SET last_access = now() - INTERVAL ? DAY WHERE user_id = ?
            ", INACTIVE_USER_DEACTIVATE_DAYS + 1, $this->user->id
        );
        $this->assertSame(
            1,
            $userMan->inactiveUserDeactivate(new Tracker()),
            'utest-one-user-inactive-deactivated',
        );
        $this->user->flush();
        $this->assertTrue($this->user->isDisabled(), 'utest-inactive-deactivated');
        $this->assertSame(
            Enum\UserStatus::disabled,
            $this->user->userStatus(),
            'user-status-disabled',
        );
    }

    public function testLastFM(): void {
        $lastfm = new Util\LastFM();
        $this->assertSame(
            'name',
            $lastfm->cleanUsername('https://last.fm/user/name'),
            'lastfm-clear-name'
        );
        $username = 'phpunit.' . randomString(6);
        $this->assertSame($username, $lastfm->cleanUsername($username), 'lastfm-clear-name');
        $this->assertNull($lastfm->username($this->user), 'lastfm-no-username');
        $this->assertSame(1, $lastfm->modifyUsername($this->user, $username), 'lastfm-create-username');
        $this->assertSame($username, $lastfm->username($this->user), 'lastfm-has-username');

        $username = "new.$username";
        $this->assertSame(1, $lastfm->modifyUsername($this->user, $username), 'lastfm-modify-username');
        $this->assertSame($username, $lastfm->username($this->user), 'lastfm-has-new-username');
        $this->assertSame(0, $lastfm->modifyUsername($this->user, $username), 'lastfm-no-modify-username');

        $this->assertSame(1, $lastfm->modifyUsername($this->user, ''), 'lastfm-remove-username');
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
        $this->assertSame(0, $rank->score(), 'userrank-score');
        $this->assertSame(0, $rank->rank('downloaded'), 'userrank-rank');
    }

    public function testUserHash(): void {
        // ensure the same text hashed by two users is different
        $second = Helper::makeUser('user.' . randomString(6), 'user');
        $this->assertNotSame(
            $this->user->hashHmac('topic', 'phpunit'),
            $second->hashHmac('topic', 'phpunit'),
            'user-hash-user-distinct'
        );
        $second->remove();
    }

    public function testUserReleaseOrder(): void {
        $this->assertSame(
            [],
            $this->user->releaseOrder([]),
            'user-no-release-order',
        );
        $extended = new ReleaseType()->extendedList();
        $this->assertCount(
            count($extended),
            array_filter(
                $this->user->releaseOrder($extended),
                fn ($r) => $r['checked'] === 0,
            ),
            'user-default-release-order',
        );
        $this->user->modifyOption(
            'SortHide',
            [
                10 /* Demo */           => true,
                6  /* Anthology */      => false,
                ARTIST_SECTION_COMPOSER => true,
                3  /* Soundtrack */     => true,
            ],
        );
        $userExtended = $this->user->releaseOrder($extended);
        $this->assertSame(
            [
                "id"      => "6_0",
                "relid"   => 6,
                "checked" => false,
                "label"   => "Anthology"
            ],
            $userExtended[1],
            'user-custom-release-order',
        );
        $this->assertSame(
            [
                "Demo",
                "Anthology",
                "Soundtrack",
            ],
            array_slice($this->user->releaseOrderLabel($extended), 0, 3),
            'user-custom-release-label',
        );
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
