<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

use Gazelle\Enum\AvatarDisplay;
use Gazelle\Enum\AvatarSynthetic;
use Gazelle\Enum\UserStatus;

class UserTest extends TestCase {
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(6), 'user');
    }

    public function tearDown(): void {
        \Gazelle\DB::DB()->prepared_query("
            DELETE FROM user_read_forum WHERE user_id = ?
            ", $this->user->id()
        );
        \Gazelle\DB::DB()->prepared_query("
            DELETE FROM users_stats_daily WHERE UserID = ?
            ", $this->user->id()
        );
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
        $admin = $userMan->find('@' . $this->user->username());
        $this->user->setField('PermissionID', SYSOP)->modify();
        $this->assertTrue($admin->isStaff(), 'admin-is-admin');
        $this->assertTrue($admin->permitted('site_upload'), 'admin-permitted-site_upload');
        $this->assertTrue($admin->permitted('site_debug'), 'admin-permitted-site_debug');
        $this->assertTrue($admin->permittedAny('site_analysis', 'site_debug'), 'admin-permitted-any-site_analysis-site_debug');
    }

    public function testFindById(): void {
        $userMan = new \Gazelle\Manager\User;
        $user = $userMan->findById($this->user->id());
        $this->assertFalse($user->isStaff(), 'user-is-not-admin');
        $this->assertStringStartsWith('user.', $user->username(), 'user-username');
        $this->assertStringEndsWith('@user.example.com', $user->email(), 'user-email');
        $this->assertFalse($user->isEnabled(), 'user-is-enabled');
        $this->assertTrue($user->isUnconfirmed(), 'user-is-confirmed');
        $this->assertFalse($user->permittedAny('site_analysis', 'site_debug'), 'utest-permittedAny-site-analysis-site-debug');
    }

    public function testAttr(): void {
        $this->assertFalse($this->user->hasUnlimitedDownload(), 'uattr-hasUnlimitedDownload');
        $this->user->toggleUnlimitedDownload(true);
        $this->assertTrue($this->user->hasUnlimitedDownload(), 'uattr-not-hasUnlimitedDownload');

        $this->assertTrue($this->user->hasAcceptFL(), 'uattr-has-FL');
        $this->user->toggleAcceptFL(false);
        $this->assertFalse($this->user->hasAcceptFL(), 'uattr-has-not-FL');

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

    public function testExternalProfile(): void {
        $externalProfile = new \Gazelle\User\ExternalProfile($this->user);
        $this->assertEquals('', $externalProfile->profile(), 'user-profile-new');
        $this->assertEquals(1, $externalProfile->modifyProfile('phpunit'), 'user-profile-create');
        $this->assertEquals('phpunit', $externalProfile->profile(), 'user-profile-set');

        $this->assertEquals(1, $externalProfile->modifyProfile('phpunit update'), 'user-profile-update');
        $this->assertEquals('phpunit update', $this->user->externalProfile()->profile(), 'user-profile-delegate');

        $this->assertEquals(1, $externalProfile->remove(), 'user-profile-remove');
        $this->assertEquals('', $externalProfile->profile(), 'user-profile-empty');
    }

    public function testPassword(): void {
        $userMan = new \Gazelle\Manager\User;
        $password = randomString(30);
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $this->assertTrue($this->user->updatePassword($password, '0.0.0.0'), 'utest-password-modify');
        $this->assertTrue($this->user->validatePassword($password), 'utest-password-validate-new');
        $this->assertCount(1, $this->user->passwordHistory(), 'utest-password-history');
        $this->assertEquals(1, $this->user->passwordCount(), 'utest-password-count');
    }

    public function testUser(): void {
        $this->assertEquals($this->user->username(), $this->user->flush()->username(), 'utest-flush-username');

        $this->assertEquals(0, $this->user->allowedPersonalCollages(), 'utest-personal-collages-allowed');
        $this->assertEquals(0, $this->user->paidPersonalCollages(), 'utest-personal-collages-paid');
        $this->assertEquals(0, $this->user->activePersonalCollages(), 'utest-personal-collages-active');
        $this->assertEquals(0, $this->user->collagesCreated(), 'utest-collage-created');
        $this->assertEquals(0, $this->user->collageUnreadCount(), 'utest-collage-unread-count');
        $this->assertEquals(0, $this->user->forumCatchupEpoch(), 'utest-forum-catchup-epoch');
        $this->assertEquals(0, $this->user->invite()->pendingTotal(), 'utest-pending-invite-count');
        $this->assertEquals(0, $this->user->downloadedOnRatioWatch(), 'utest-download-ratio-watch');
        $this->assertEquals(0, $this->user->seedingSize(), 'utest-seeding-size');
        $this->assertEquals(0, $this->user->torrentDownloadCount(0), 'utest-torrent-download-count');
        $this->assertEquals(0, $this->user->torrentRecentRemoveCount(1), 'utest-torrent-recent-remove-count');
        $this->assertEquals(0, $this->user->trackerIPCount(), 'utest-tracker-ipaddr-total');
        $this->assertEquals(0.0, $this->user->requiredRatio(), 'utest-required-ratio');
        $this->assertEquals(1, $this->user->siteIPCount(), 'utest-site-ipaddr-total');
        $this->assertEquals('', $this->user->forbiddenForumsList(), 'utest-forbidden-forum-list');
        $this->assertEquals([], $this->user->tagSnatchCounts(), 'utest-tag-snatch-counts');
        $this->assertEquals([], $this->user->tokenList(new \Gazelle\Manager\Torrent, 0, 0), 'utest-token-list');
        $this->assertEquals(USER, $this->user->primaryClass(), 'utest-primary-class');

        $this->assertTrue($this->user->forumAccess(0, 0), 'utest-forum-access-low');
        $this->assertFalse($this->user->forumAccess(0, 10000), 'utest-forum-access-high');

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
        $this->assertFalse($this->user->onRatioWatch(), 'utest-personal-on-ratio-watch');
        $this->assertFalse($this->user->permitted('site_debug'), 'utest-permitted-site-debug');

        $this->assertNull($this->user->lastAccess(), 'utest-last-access');
        $this->assertNull($this->user->warningExpiry(), 'utest-warning-expiry');
        $this->assertNull($this->user->warningExpiry(), 'utest-warning-expiry');

        $this->assertEquals([], $this->user->snatch()->recentSnatchList(), 'utest-recent-snatch');
        $this->assertEquals([], $this->user->recentUploadList(), 'utest-recent-upload');
        $this->assertInstanceOf(Gazelle\User\Snatch::class, $this->user->snatch()->flush(), 'utest-flush-recent-snatch');
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
        $this->assertStringContainsString('phpunit staff note', $this->user->staffNotes(), 'utest-staff-note');

        $this->assertFalse($this->user->setTitle(str_repeat('x', USER_TITLE_LENGTH + 1)), 'utest-title-too-long');
        $this->assertTrue($this->user->setTitle('custom title'), 'utest-set-title');
        $this->user->modify();
        $this->assertEquals('custom title', $this->user->title(), 'utest-title');
        $this->assertTrue($this->user->removeTitle()->modify(), 'utest-remove-title');
        $this->assertNull($this->user->title(), 'utest-null-title');
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

        $url = 'https://www.example.com/avatar.jpg';
        $this->assertTrue($this->user->setField('avatar', $url)->modify(), 'utest-avatar-set');
        $this->assertEquals($url, $this->user->avatar(), 'utest-avatar-url');
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
        $this->assertTrue($this->user->setField('lock-type', STAFF_LOCKED)->modify(), 'utest-set-locked');
        $this->user->flush();
        $this->assertTrue($this->user->isLocked(), 'utest-is-now-locked');
        $this->assertEquals(STAFF_LOCKED, $this->user->lockType(), 'utest-lock-type');
        $this->assertTrue($this->user->setField('lock-type', 0)->modify(), 'utest-set-unlocked');
        $this->user->flush();
        $this->assertFalse($this->user->isLocked(), 'utest-is-unlocked');
    }

    public function testNextClass(): void {
        $this->assertEquals(\Gazelle\Enum\UserStatus::unconfirmed, $this->user->userStatus(), 'utest-user-status-unconfirmed');
        $this->user->setField('Enabled', UserStatus::enabled->value)->modify();
        $this->assertEquals(\Gazelle\Enum\UserStatus::enabled, $this->user->userStatus(), 'utest-user-status-enabled');

        $manager = new Gazelle\Manager\User;
        $next = $this->user->nextClass($manager);
        $this->assertEquals('Member', $next['class'], 'user-next-class-is-member');

        $goal = $next['goal'];
        $this->assertCount(3, $goal, 'user-next-requirements');
        $this->assertStringContainsString('100%', $goal['Ratio']['percent'],  'user-next-ratio');
        $this->assertStringContainsString('0%',   $goal['Time']['percent'],   'user-next-time');
        $this->assertStringContainsString('30%',  $goal['Upload']['percent'], 'user-next-upload');

        $this->user->setField('created', date('Y-m-d H:i:s', strtotime('-2 day')))->modify();
        $next = $this->user->nextClass($manager);
        $this->assertStringContainsString('29%', $next['goal']['Time']['percent'], 'user-next-closer-time');
        $this->user->setField('created', date('Y-m-d H:i:s', strtotime('-7 day')))->modify();
        $next = $this->user->nextClass($manager);
        $this->assertStringContainsString('100%', $next['goal']['Time']['percent'], 'user-next-has-time');

        $this->user->addBounty(7 * 1024 * 1024 * 1024);
        $next = $this->user->nextClass($manager);
        $this->assertStringContainsString('100%',  $next['goal']['Upload']['percent'], 'user-next-has-upload');

        $manager->promote();
        $this->assertEquals('Member', $this->user->flush()->userclassName(), 'user-promoted-to-member');
    }

    public function testStylesheet(): void {
        $manager = new \Gazelle\Manager\Stylesheet;
        $list = $manager->list();
        $this->assertGreaterThan(5, $list, 'we-can-haz-stylesheets');
        $this->assertEquals(count($list), count($manager->usageList('name', 'ASC')), 'stylesheet-list-usage');

        $first = current($list);
        $url   = SITE_URL . 'static/bogus.css';
        $stylesheet = new \Gazelle\User\Stylesheet($this->user);
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
        $ipaddr = implode('.', ['127', random_int(0, 255), random_int(0, 255), random_int(0, 255)]);
        $_SERVER['REMOTE_ADDR'] = $ipaddr;
        $watch  = new \Gazelle\LoginWatch($ipaddr);
        $this->assertEquals(1, $watch->nrAttempts(), 'loginwatch-init-attempt');
        $this->assertEquals(0, $watch->nrBans(), 'loginwatch-init-ban');

        $login  = new \Gazelle\Login;
        $result = $login->login(
            username: 'email@example.com',
            password: 'password',
            watch:    $watch,
        );
        $this->assertNull($result, 'login-bad-username');
        $this->assertEquals($login->error(), \Gazelle\Login::ERR_CREDENTIALS, 'login-error-username');
        $this->assertEquals('email@example.com', $login->username(), 'login-username');
        $this->assertEquals($ipaddr, $login->ipaddr(), 'login-ipaddr');
        $this->assertFalse($login->persistent(), 'login-persistent');
        $this->assertEquals(2, $watch->nrAttempts(), 'loginwatch-attempt');

        $result = $login->login(
            username: $this->user->username(),
            password: 'password',
            watch:    $watch,
        );
        $this->assertNull($result, 'login-bad-password');
        $this->assertEquals($login->error(), \Gazelle\Login::ERR_CREDENTIALS, 'login-error-password');
        $this->assertEquals(3, $watch->nrAttempts(), 'loginwatch-more-attempt');

        $this->assertGreaterThan(0, count($watch->activeList('1', 'ASC', 10, 0)), 'loginwatch-active-list');
        $this->assertGreaterThan(0, $watch->clearAttempts(), 'loginwatch-clear');
        $this->assertEquals(0, $watch->nrAttempts(), 'loginwatch-no-attempts');
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

        $this->assertEquals(0, $this->user->announceKeyCount(), 'utest-announce-key-count');
        $this->assertEquals(32, strlen($key), 'utest-announce-key');
        $this->assertStringStartsWith(ANNOUNCE_HTTPS_URL, $url, 'utest-announce-url-begin');
        $this->assertStringEndsWith('/announce', $url, 'utest-announce-url-end');
        $this->assertCount(0, $this->user->announceKeyHistory(), 'utest-announce-key-history');

        $new = randomString(32);
        $ipaddr = '127.2.2.2';
        $this->assertEquals(1, $this->user->modifyAnnounceKeyHistory($key, $new, $ipaddr), 'utest-announce-key-modify');
        $this->assertEquals(1, $this->user->announceKeyCount(), 'utest-announce-key-new-count');
        $this->assertCount(1, $this->user->announceKeyHistory(), 'utest-announce-key-new-history');
        $history = current($this->user->announceKeyHistory());
        $this->assertEquals($key, $history['old'], 'utest-announce-key-history-old');
        $this->assertEquals($new, $history['new'], 'utest-announce-key-history-new');
        $this->assertEquals($ipaddr, $history['ipaddr'], 'utest-announce-key-history-ipaddr');
        $this->assertTrue(Helper::recentDate($history['date']), 'utest-announce-key-history-date');
    }

    public function testInactive(): void {
        $userMan = new \Gazelle\Manager\User;
        $db      = \Gazelle\DB::DB();

        $this->user->setField('Enabled', UserStatus::enabled->value)->modify();
        $db->prepared_query("
            INSERT INTO user_last_access (user_id, last_access) VALUES (?, now() - INTERVAL ? DAY)
            ", $this->user->id(), INACTIVE_USER_WARN_DAYS + 1
        );
        $this->user->flush();
        $this->assertEquals(1, $userMan->inactiveUserWarn(new \Gazelle\Util\Mail), 'utest-one-user-inactive-warned');
        $this->assertTrue($this->user->hasAttr('inactive-warning-sent'), 'utest-inactive-warned');

        $db->prepared_query("
            UPDATE user_last_access SET last_access = now() - INTERVAL ? DAY WHERE user_id = ?
            ", INACTIVE_USER_DEACTIVATE_DAYS + 1, $this->user->id()
        );
        $this->assertEquals(1, $userMan->inactiveUserDeactivate(new \Gazelle\Tracker), 'utest-one-user-inactive-deactivated');
        $this->user->flush();
        $this->assertTrue($this->user->isDisabled(), 'utest-inactive-deactivated');
    }

    public function testLastFM(): void {
        $lastfm   = new \Gazelle\Util\LastFM;
        $username = 'phpunit.' . randomString(6);
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

    public function testStats(): void {
        $eco = new \Gazelle\Stats\Economic;
        $eco->flush();

        $total    = $eco->tokenTotal();
        $stranded = $eco->tokenStrandedTotal();
        $this->user->setField('Enabled', UserStatus::enabled->value)->modify();
        $this->assertTrue($this->user->updateTokens(23), 'utest-stats-token-5');

        $eco->flush();
        $this->assertEquals(23 + $total, $eco->tokenTotal(), 'utest-stats-total-tokens');
        $this->assertEquals($stranded, $eco->tokenStrandedTotal(), 'utest-stats-total-stranded-tokens');

        $disabled = $eco->userDisabledTotal();
        $this->user->setField('Enabled', UserStatus::disabled->value)->modify();
        $eco->flush();
        $this->assertEquals(23 + $stranded, $eco->tokenStrandedTotal(), 'utest-stats-total-disabled-stranded-tokens');
        $this->assertEquals(1 + $disabled, $eco->userDisabledTotal(), 'utest-stats-user-disabled-total');

        $stats = new \Gazelle\Stats\Users;
        $this->assertTrue($stats->newUsersAllowed($this->user), 'user-stats-new-users');
        $this->assertGreaterThan(0, $stats->refresh(), 'user-stats-refresh');
        $this->assertGreaterThan(0, $stats->registerActivity('users_stats_daily', 10), 'user-stats-register');

        $this->assertIsArray($stats->flow(), 'user-stats-flow');
        $this->assertIsArray($stats->browserDistributionList(), 'user-stats-browser-list');
        $this->assertIsArray($stats->browserDistribution(), 'user-stats-browser-dist');
        $this->assertIsArray($stats->userclassDistributionList(), 'user-stats-userclass-list');
        $this->assertIsArray($stats->userclassDistribution(), 'user-stats-userclass-dist');
        $this->assertIsArray($stats->platformDistributionList(), 'user-stats-platform-list');
        $this->assertIsArray($stats->platformDistribution(), 'user-stats-platform-dist');
        $this->assertIsArray($stats->geodistribution(), 'user-stats-geo-dist');
        $this->assertIsArray($stats->peerStat(), 'user-stats-peer');
        $this->assertIsArray($stats->stockpileTokenList(10), 'user-stats-stockpile');
        $this->assertIsArray($stats->browserList(), 'user-stats-browser');
        $this->assertIsArray($stats->operatingSystemList(), 'user-stats-os');

        $this->assertIsInt($stats->leecherTotal(), 'user-stats-leecher');
        $this->assertIsInt($stats->peerTotal(), 'user-stats-peer');
        $this->assertIsInt($stats->seederTotal(), 'user-stats-seeder');
        $this->assertIsInt($stats->snatchTotal(), 'user-stats-snatch');
        $this->assertIsInt($stats->enabledUserTotal(), 'user-stats-enabled');

        $this->assertIsArray($stats->activityStat(), 'user-stats-activity');
        $this->assertIsInt($stats->dayActiveTotal(), 'user-stats-active-day');
        $this->assertIsInt($stats->weekActiveTotal(), 'user-stats-active-week');
        $this->assertIsInt($stats->monthActiveTotal(), 'user-stats-active-month');
    }

    public function testUserRank(): void {
        $rank = new Gazelle\UserRank(
            new Gazelle\UserRank\Configuration(RANKING_WEIGHT),
            [
                'uploaded'   => STARTING_UPLOAD,
                'downloaded' => 1,
                'uploads'    => 0,
                'requests'   => 0,
                'posts'      => 0,
                'bounty'     => 0,
                'artists'    => 0,
                'collage'    => 0,
                'votes'      => 0,
                'bonus'      => 0,
                'comment-t'  => 0,
            ]
        );
        $this->assertEquals(0, $rank->score(), 'userrank-score');
        $this->assertEquals(1, $rank->rank('downloaded'), 'userrank-rank');
    }
}
