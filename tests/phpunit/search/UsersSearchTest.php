<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use GazelleUnitTest\Helper;
use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserStatus;

class UsersSearchTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList[] = Helper::makeUser('advs.' . randomString(10), 'advsearch');
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            Helper::removeUser($user);
        }
    }

    public function testSearchUsersBonus(): void {
        $user = array_first($this->userList);
        $user->stats()->incrementSeedFloat(Enum\UserSummarySeed::bpAccrualHour, 5.0);
        new User\Bonus($user)->setPoints(10000);
        $bonus = new Search\Users([
            'bprate-min'  => 5.5, // test flip
            'bprate-max'  => 4.5,
            'bptotal-min' => 9500,
            'bptotal-max' => 10500,
            'view'        => 'username.bprate.bptotal',
        ]);
        $this->assertSame(
            1,
            $bonus->total(),
            'advs-bonus-total',
        );
        $this->assertEquals(
            [[
                'c0' => $user->id,
                'c1' => $user->stats()->bpHourlyAccrual(),
                'c2' => $user->bonusPointsTotal(),
            ]],
            $bonus->page(2, 0),
            'advs-bonus-page',
        );
    }

    public function testSearchUsersDisable(): void {
        $user = array_first($this->userList);
        $user->toggleAttr('disable-irc', true);
        $user->toggleAttr('disable-avatar', true);
        $attr    = new Manager\User()->attrList();
        $disable = [
            array_first(array_filter($attr, fn ($a) => $a['name'] === 'disable-avatar'))['id'],
            array_first(array_filter($attr, fn ($a) => $a['name'] === 'disable-irc'))['id'],
        ];
        $disableAll = new Search\Users([
            'disable'    => $disable,
            'disable-op' => 'all'
        ]);
        $this->assertSame(
            1,
            $disableAll->total(),
            'advs-disable-all-total',
        );

        $disable[] = array_first(array_filter($attr, fn ($a) => $a['name'] === 'disable-wiki'))['id'];
        $disableMore = new Search\Users([
            'disable'    => $disable,
            'disable-op' => 'all',
            'view'       => 'joined.disable',
        ]);
        $this->assertSame(
            0,
            $disableMore->total(),
            'advs-disable-more-total',
        );
        $disableAny = new Search\Users([
            'disable'    => $disable,
            'disable-op' => 'any',
            'userid-min' => $user->id, // to cut out any crosstalk
            'view'       => 'username.disable',
        ]);
        $this->assertSame(
            1,
            $disableAny->total(),
            'advs-disable-any-total',
        );
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => ['disable-avatar', 'disable-irc'],
            ]],
            $disableAny->page(2, 0),
            'advs-disable-any-page',
        );
    }

    public function testSearchUsersEnabled(): void {
        $user = array_first($this->userList);
        $unconfirmed = new Search\Users([
            'enabled'     => 'unconfirmed',
            'username'    => $user->username(),
            'username-op' => 'equals',
            'view'        => 'username.lastseen'
        ]);
        $this->assertSame(
            1,
            $unconfirmed->total(),
            'advs-unconfirmed-equals-total-result',
        );
        $this->assertSame(
            'select um."ID" as c0, ula.last_access as c1, um."Username" as username from relay.users_main um left join relay.user_last_access ula on (ula.user_id = um."ID") where um."Enabled" = $1 and um."Username" = $2 order by username asc, c1 desc  limit $3 offset $4',
            $unconfirmed->pageSql(),
            'advs-unconfirmed-page-sql',
        );
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => null,
            ]],
            $unconfirmed->page(2, 0),
            'advs-unconfirmed-equals-page-result',
        );

        $user->setField('Enabled', UserStatus::enabled->value)->modify();
        // totals of a specific search are cached for three minutes
        global $Cache;
        $Cache->delete_value($unconfirmed->totalKey());
        $this->assertSame(
            0,
            $unconfirmed->total(),
            'advs-enabled-equals-total-result',
        );
        $user->setField('Enabled', UserStatus::disabled->value)->modify();
        $disabled = new Search\Users([
            'enabled'     => 'disabled',
            'username'    => $user->username(),
            'username-op' => 'equals',
            'view'        => 'username.lastseen.enabled'
        ]);
        $Cache->delete_value($disabled->totalKey());
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => null,
                'c2' => UserStatus::disabled->value,
            ]],
            $disabled->page(2, 0),
            'advs-disabled-equals-page-result',
        );
    }

    public function testSearchUsersEmail(): void {
        $user  = $this->userList[0];
        $email = new Search\Users([
            'email'    => $user->email(),
            'email-op' => 'equals',
            'view'     => 'username',
        ]);
        $this->assertSame(
            1,
            $email->total(),
            'advs-email-equals-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $user->id,
            ]],
            $email->page(2, 0),
            'advs-email-equals-page-result',
        );

        $oldEmail = $user->email();
        $newEmail = 'advs.' . randomString(10) . '@example.net';
        $user->setField('Email', $newEmail)->modify();
        global $Cache;
        $notFound = new Search\Users([
            'email'    => $oldEmail,
            'email-op' => 'equals',
            'view'     => 'username',
        ]);
        $Cache->delete_value($notFound->totalKey());
        $this->assertSame(
            0,
            $notFound->total(),
            'advs-email-hist-not-found',
        );
        $found = new Search\Users([
            'email'      => $oldEmail,
            'email-op'   => 'equals',
            'view'       => 'username',
            'email-hist' => 'on',
        ]);
        $this->assertSame(
            1,
            $found->total(),
            'advs-email-hist-found',
        );
        $not = new Search\Users([
            'email'      => '@example.com',
            'email-op'   => 'contains',
            'email-not'  => 'on',
            'userid-min' => 3, // step past CI default users
            'view'       => 'userid.email',
        ]);
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => $user->email(),
            ]],
            $not->page(1, 0),
            'advs-email-not-page-result',
        );
    }

    public function testSearchUsersFltoken(): void {
        $n    = 600;
        $user = array_first($this->userList);
        $user->updateTokens($n);
        $user->flush();
        $fltoken = new Search\Users([
            'fltoken-min' => $n - 1,
            'fltoken-max' => $n + 1,
            'view'       => 'userid.fltoken',
        ]);
        $this->assertSame(
            'select count(*) from relay.users_main um inner join relay.user_flt uf on (uf.user_id = um."ID") where uf.tokens >= $1 and uf.tokens <= $2',
            $fltoken->totalSql(),
            'advs-fltoken-sql-total',
        );
        $this->assertSame(
            1,
            $fltoken->total(),
            'advs-fltoken-total',
        );
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => $user->tokenCount(),
            ]],
            $fltoken->page(2, 0),
            'advs-fltoken-page',
        );
    }

    public function testSearchUsersFlRxTx(): void {
        $this->userList[] = Helper::makeUser('advs.' . randomString(10), 'advsearch', enable: true);
        [$u1, $u2] = $this->userList;
        $item = new Manager\Bonus()->findBonusItemByLabel('other-3');
        $n = 5;
        foreach (range(1, $n) as $gift) {
            $item->purchase($u1, 0, ['receiver' => $u2, 'message' => 'phpunit']);
        }
        $flrx = new Search\Users([
            'flrx-min'    => 300,
            'flrx-max'    => 200,
            'username'    => '^(' . implode('|', array_map(fn ($u) => $u->username(), $this->userList)) . ')$',
            'username-op' => 'regexp',
            'view'        => 'username.fltoken.flrx',
        ]);
        $this->assertSame(
            1,
            $flrx->total(),
            'advs-flrx-total',
        );
        $this->assertSame(
            [[
                'c0' => $u2->id,
                'c1' => $item->amount() * $n,
                'c2' => $item->amount() * $n,
            ]],
            $flrx->page(2, 0),
            'advs-flrx-page-result',
        );

        $fltx = new Search\Users([
            'fltx-min'    => 300,
            'fltx-max'    => 200,
            'username'    => '^(' . implode('|', array_map(fn ($u) => $u->username(), $this->userList)) . ')$',
            'username-op' => 'regexp',
            'view'        => 'username.fltoken.fltx',
        ]);
        $this->assertSame(
            1,
            $fltx->total(),
            'advs-fltx-total',
        );
        $this->assertSame(
            [[
                'c0' => $u1->id,
                'c1' => 0,
                'c2' => $item->amount() * $n,
            ]],
            $fltx->page(2, 0),
            'advs-fltx-page-result',
        );
    }

    public function testSearchUsersInviterEquals(): void {
        // the second user is invited by the first
        $this->userList[] = Helper::makeUser('advs.' . randomString(10), 'advsearch');
        $this->userList[0]->setField('Username', 'def')->modify();
        $this->userList[1]->setField('inviter_user_id', $this->userList[0]->id)->modify();
        new Stats\Users()->refresh();
        $inviter = new Search\Users([
            'inviter'    => $this->userList[0]->username(),
            'inviter-op' => 'equals',
            'view'       => 'username.inviter.downloadgb.uploadgb'
        ]);
        $this->assertSame(
            'select count(*) from relay.users_main um left join relay.users_main uinv on (uinv."ID" = um.inviter_user_id) inner join relay.users_leech_stats uls on (uls."UserID" = um."ID") where uinv."Username" = $1',
            $inviter->totalSql(),
            'advs-inviter-equals-total-sql',
        );
        $this->assertSame(
            1,
            $inviter->total(),
            'advs-inviter-equals-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[1]->id, // inviter
                'c1' => $this->userList[0]->id, // invitee
                'c2' => $this->userList[0]->downloadedSize(),
                'c3' => $this->userList[0]->uploadedSize(),
            ]],
            $inviter->page(2, 0),
            'advs-inviter-equals-page-result',
        );

        $invitedLow = new Search\Users([
            'invited-min' => 2000, // to check swap
            'invited-max' => 2,
            'view'        => 'username.invited'
        ]);
        $this->assertSame(
            'select count(*) from relay.users_main um inner join user_summary_misc usm on (usm.id_user = um."ID") where usm.invited >= $1 and usm.invited <= $2',
            $invitedLow->totalSql(),
            'advs-invited-min-too-many-sql',
        );
        $this->assertSame(
            0,
            $invitedLow->total(),
            'advs-invited-min-too-many-total',
        );

        $invitedOk = new Search\Users([
            'invited-min' => 1,
            'joined-from' => new \DateTimeImmutable('1 minute ago')->format('Y-m-d H:i:s'),
            'view'        => 'username.invited'
        ]);
        global $Cache;
        $Cache->delete_value($invitedOk->totalKey());
        $this->assertSame(
            1,
            $invitedOk->total(),
            'advs-invited-min-ok-total',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[0]->id,
                'c1' => 1,
            ]],
            $invitedOk->page(2, 0),
            'advs-invited-min-ok-page',
        );
    }

    public function testSearchUsersInviteSource(): void {
        $user          = $this->userList[0];
        $sourceManager = new Manager\InviteSource();
        $sourceName    = 'advs.' . randomString(10);
        $sourceId      = $sourceManager->create($sourceName);
        $sourceManager->modifyInviterConfiguration($user, [$sourceId]);

        $user->setField('PermissionID', MEMBER)
            ->setField('Invites', 1)
            ->modify();
        $profile = 'https://example.com/user/' . random_int(1000, 9999);
        $invite  = new Manager\Invite()->create(
            user:   $user,
            email:  randomString(10) . "@advs.example.com",
            notes:  'phpunit notes',
            reason: $profile,
            source: $sourceName,
        );
        $sourceManager->createPendingInviteSource($sourceId, $invite->key());
        $this->userList[] = new UserCreator()
            ->setUsername('create.' . randomString(6))
            ->setEmail(randomString(6) . '@example.com')
            ->setPassword(randomString(10))
            ->setInviteKey($invite->key())
            ->create();
        // phew

        $inviteSrc = new Search\Users([
            'invsource' => $sourceId,
            'view'      => 'username.inviter.invsource.extprofile'
        ]);
        $this->assertSame(
            1,
            $inviteSrc->total(),
            'advs-invite-source-total',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[1]->id,
                'c1' => $this->userList[0]->id,
                'c2' => $sourceName,
                'c3' => $profile,
            ]],
            $inviteSrc->page(2, 0),
            'advs-invite-source-page',
        );

        $extProfile = new Search\Users([
            'extprofile'    => $profile,
            'extprofile-op' => 'equal',
            'view'          => 'username.invsource.extprofile'
        ]);
        $this->assertSame(
            1,
            $extProfile->total(),
            'advs-extprofile-total',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[1]->id,
                'c1' => $sourceName,
                'c2' => $profile,
            ]],
            $extProfile->page(2, 0),
            'advs-extprofile-page',
        );

        // tidy up
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM inviter_has_invite_source WHERE invite_source_id = ?
            ", $sourceId
        );
        $db->prepared_query("
            DELETE FROM user_has_invite_source WHERE invite_source_id = ?
            ", $sourceId
        );
        $sourceManager->remove($sourceId);
    }

    public function testSearchUsersInviter(): void {
        // put everyone in the same domain to avoid crosstalk
        $domain = '@php.unit';
        $this->userList[0]
            ->setField('Email', "tsui1$domain")
            ->modify();
        // the second user is invited by the first
        $this->userList[] = Helper::makeUser('advs.' . randomString(10), 'advsearch')
            ->setField('Email', "tsui2$domain")
            ->setField('inviter_user_id', $this->userList[0]->id);
        $this->userList[1]->modify();
        // the third user is invited by the second
        $this->userList[] = Helper::makeUser('advs.' . randomString(10), 'advsearch')
            ->setField('Email', "tsui3$domain")
            ->setField('inviter_user_id', $this->userList[1]->id);
        $this->userList[2]->modify();

        $inviter = new Search\Users([
            'inviter'    => "({$this->userList[0]->username()}|{$this->userList[1]->username()})",
            'inviter-op' => 'regexp',
            'email'      => $domain,
            'email-op'   => 'contains',
            'view'       => 'userid.inviter.email'
        ]);
        $this->assertSame(
            [
                [
                    'c0' => $this->userList[1]->id, // invitee
                    'c1' => $this->userList[0]->id, // inviter
                    'c2' => $this->userList[1]->email(),
                ],
                [
                    'c0' => $this->userList[2]->id, // invitee
                    'c1' => $this->userList[1]->id, // inviter
                    'c2' => $this->userList[2]->email(),
                ],
            ],
            $inviter->page(2, 0),
            'advs-inviter-regexp-page-result',
        );
        $this->assertSame(
            2,
            $inviter->total(),
            'advs-inviter-regexp',
        );

        $minmax = new Search\Users([
            'inviter-min' => 10, // test the value swap at the same time
            'inviter-max' => 1,
            'joined-from' => new \DateTimeImmutable('5 seconds ago')->format('Y-m-d H:i:s'),
            'userid-min'  => 3, // step past CI default users
            'view'        => 'userid.email'
        ]);
        $this->assertSame(
            [
                [
                    'c0' => $this->userList[0]->id,
                    'c1' => $this->userList[0]->email(),
                ],
                [
                    'c0' => $this->userList[1]->id,
                    'c1' => $this->userList[1]->email(),
                ],
            ],
            $minmax->page(2, 0),
            'advs-inviter-minmax-page-result',
        );
        $invited = new Search\Users([
            'invited-min' => 1,
            'joined-from' => new \DateTimeImmutable('5 minutes ago')->format('Y-m-d H:i:s'),
            'view'        => 'joined'
        ]);
        new Stats\Users()->refresh();
        global $Cache;
        $Cache->delete_value($invited->totalKey());
        $this->assertSame(
            2,
            $invited->total(),
            'advs-invited-total'
        );
        $this->assertEqualsCanonicalizing(
            [
                [
                    'c0' => new \DateTimeImmutable($this->userList[1]->created()),
                ],
                [
                    'c0' => new \DateTimeImmutable($this->userList[0]->created()),
                ],
            ],
            $invited->page(2, 0),
            'advs-invited-page',
        );
    }

    public function testSearchUsersJoined(): void {
        $joinedTo = new Search\Users([
            'joined-to' => new \DateTimeImmutable('10 minutes ago')->format('Y-m-d H:i:s'),
            'view'      => 'joined'
        ]);
        $this->assertGreaterThanOrEqual(
            1, // must be able to find at least the sysop
            $joinedTo->total(),
            'advs-joined-to-total-result',
        );

        $this->userList[0]->setField('Username', 'alpha')->modify();
        $joinedFrom = new Search\Users([
            'joined-from' => new \DateTimeImmutable('tomorrow')->format('Y-m-d H:i:s'), // test flip
            'joined-to'   => new \DateTimeImmutable('10 seconds ago')->format('Y-m-d H:i:s'),
            'userid-min'  => 3, // step past CI default users
            'view'        => 'joined.username'
        ]);
        $this->assertGreaterThanOrEqual(
            1,
            $joinedFrom->total(),
            'advs-joined-from-total-result',
        );
        $this->assertEquals(
            [[
                'c0' => new \DateTimeImmutable($this->userList[0]->created()),
                'c1' => $this->userList[0]->id,
            ]],
            $joinedFrom->page(2, 0),
            'advs-joined-from-page-result',
        );
    }

    public function testSearchUsersMFA(): void {
        $user = array_first($this->userList);
        $mfaNo = new Search\Users([
            'email'    => $user->email(),
            'email-op' => 'equals',
            'mfa'      => 'no',
            'view'     => 'email.mfa',
        ]);
        $this->assertSame(
            1,
            $mfaNo->total(),
            'advs-mfano-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => null,
            ]],
            $mfaNo->page(2, 0),
            'advs-mfano-page',
        );

        $manager = new Manager\UserToken();
        $init    = $user->MFA();
        $init->create($manager, $init->generateSessionSecret());
        $mfaYes = new Search\Users([
            'email'    => $user->email(),
            'email-op' => 'equals',
            'mfa'      => 'yes',
            'view'     => 'email.mfa',
        ]);
        $this->assertSame(
            1,
            $mfaYes->total(),
            'advs-mfayes-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => $user->id, // not null is all we need to display the correct glyph
            ]],
            $mfaYes->page(2, 0),
            'advs-mfayes-page',
        );
        $manager->removeUser($user);
    }

    public function testSearchUsersMinMax(): void {
        $user = array_first($this->userList);
        $user->stats()->incrementTGroup(Enum\UserSummaryTGroup::downloadTotal, 2345);
        $user->stats()->incrementTGroup(Enum\UserSummaryTGroup::upload, 5432);
        $minmax = new Search\Users([
            'tdownload-min' => 3000, // to check swap
            'tdownload-max' => 2000,
            'uploads-min'   => 5000,
            'uploads-max'   => 6000,
            'view'          => 'email.tdownload.uploads'
        ]);
        $this->assertSame(
            1,
            $minmax->total(),
            'advs-1-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => 2345,
                'c2' => 5432,
            ]],
            $minmax->page(2, 0),
            'advs-minmax-page',
        );
    }

    public function testSearchUsersMinMaxGiB(): void {
        $user = array_first($this->userList);
        $user->setField('leech_download', 3456 * 1024 ** 3)
            ->setField('leech_upload', 6543 * 1024 ** 3)
            ->modify();
        $minmaxgb = new Search\Users([
            'downloadgb-min' => 4000,
            'downloadgb-max' => 3000,
            'uploadgb-min'   => 6000,
            'uploadgb-max'   => 7000,
            'view'          => 'email.downloadgb.uploadgb'
        ]);
        $this->assertSame(
            1,
            $minmaxgb->total(),
            'advs-minmaxgb-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => $user->downloadedSize(),
                'c2' => $user->uploadedSize(),
            ]],
            $minmaxgb->page(2, 0),
            'advs-minmaxgb-page',
        );
    }

    public function testSearchUsersReferral(): void {
        $user = array_first($this->userList);
        $site = 'TEST';
        $db   = DB::DB();
        $db->prepared_query("
            INSERT IGNORE INTO referral_accounts
                (Site, URL, User, Password, Cookie, Active, Type)
            VALUES
                (?,    '',  '',   '',       '',     false,  0)
            ", $site
        );
        $referralId = $user->id + 100;
        $db->prepared_query("
            INSERT INTO referral_users
                   (UserID, Username, Site, IP, InviteKey)
            VALUES (?,    ?,           ?,     ?,  ?)
            ", $user->id, $referralId, $site, '', ''
        );
        $refer = new Search\Users([
            'referral'     => $site,
            'referral-uid' => $referralId,
            'view'         => 'username.referral'
        ]);
        $this->assertSame(
            1,
            $refer->total(),
            'advs-refer-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => "$site:$referralId",
            ]],
            $refer->page(2, 0),
            'advs-refer-page',
        );
        $loose = new Search\Users([
            'referral'     => $site,
            'referral-uid' => '', // uid unknown
            'view'         => 'username.referral'
        ]);
        $this->assertSame(
            1,
            $loose->total(),
            'advs-refer-loose-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->id,
                'c1' => "$site:$referralId",
            ]],
            $loose->page(2, 0),
            'advs-refer-loose-page',
        );

        $db->prepared_query("
            DELETE FROM referral_users WHERE Site = ?
            ", $site
        );
        $db->prepared_query("
            DELETE FROM referral_accounts WHERE Site = ?
            ", $site
        );
    }

    public function testSearchUsersSiteip(): void {
        $ip = '169.254.1.1';
        $this->userList[0]->history()->registerSiteIp('169.254.1.1');
        $siteip = new Search\Users([
            'siteip'    => '169.254/16',
            'siteip-op' => 'subnet',
            'view'      => 'username.siteip',
        ]);
        $this->assertSame(
            1,
            $siteip->total(),
            'advs-siteip-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[0]->id,
                'c1' => $ip,
            ]],
            $siteip->page(2, 0),
            'advs-siteip-page-result',
        );
    }

    public function testSearchUsersTrackerip(): void {
        $user = array_first($this->userList);
        $ip   = '169.254.30.30';
        $db   = DB::DB();
        $db->prepared_query("
            INSERT INTO xbt_files_users
                   (uid, ip, peer_id, fid, useragent, mtime, remaining, active, timespent)
            VALUES (?,   ?,  ?,       0,   'phpunit', unix_timestamp(now() - interval 10 second), 0, 1, 1)
            ", $user->id, $ip, randomString(20),
        );
        $trackerip = new Search\Users([
            'trackerip'      => '169.254.30',
            'trackerip-op'   => 'prefix',
            'trackerip-hist' => false,
            'view'           => 'email.trackerip',
        ]);
        $this->assertSame(
            1,
            $trackerip->total(),
            'advs-trackerip-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => $ip,
            ]],
            $trackerip->page(2, 0),
            'advs-trackerip-page-result',
        );
        $trackerview = new Search\Users([
            'email'    => $user->email(),
            'email-op' => 'equals',
            'view'     => 'email.trackerip',
        ]);
        $this->assertSame(
            1,
            $trackerview->total(),
            'advs-trackerview-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => $ip,
            ]],
            $trackerview->page(2, 0),
            'advs-trackerview-page-result',
        );
        $db->prepared_query("
            DELETE FROM xbt_files_users WHERE uid = ?
            ", $user->id
        );
    }

    public function testSearchUsersHistTrackerip(): void {
        $user   = array_first($this->userList);
        $histip = '169.254.77.77';
        $db     = DB::DB();
        $db->prepared_query("
            INSERT INTO xbt_snatched
                   (uid, IP, fid, tstamp)
            VALUES (?,   ?,  0,   unix_timestamp(now() - interval 10 second))
            ", $user->id, $histip,
        );
        $trhistip = new Search\Users([
            'trackerip'      => '169.254.77',
            'trackerip-op'   => 'prefix',
            'trackerip-hist' => true,
            'view'           => 'email.trackerip'
        ]);
        $this->assertSame(
            1,
            $trhistip->total(),
            'advs-trhistip-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => $histip,
            ]],
            $trhistip->page(2, 0),
            'advs-thistip-page-result',
        );
        // Viewing a tracker IP will return the currently seeding IP.
        // Falling back to show the IP of a snatch from a previous host
        // if the user is not currently seeding does not make a whole
        // lot of sense and would be slow af. Just search for it.
        $db->prepared_query("
            DELETE FROM xbt_snatched WHERE uid = ?
            ", $user->id
        );
    }

    public function testSearchUsersSecondaryclass(): void {
        $this->userList[] = Helper::makeUser('advs.' . randomString(10), 'advsearch');
        $this->userList[0]->privilege()->addSecondaryClass(FLS_TEAM);
        $this->userList[1]->privilege()->addSecondaryClass(FLS_TEAM);
        $this->userList[1]->privilege()->addSecondaryClass(INTERVIEWER);
        $secclass = new Search\Users([
            'secclass'    => [FLS_TEAM, INTERVIEWER],
            'joined-from' => new \DateTimeImmutable('1 minute ago')->format('Y-m-d H:i:s'),
            'view'        => 'userid.secclass'
        ]);
        $this->assertSame(
            2,
            $secclass->total(),
            'advs-secclass-total'
        );
        $this->assertEqualsCanonicalizing(
            [
                [
                    'c0' => $this->userList[0]->id,
                    'c1' => 'FLS',
                ],
                [
                    'c0' => $this->userList[1]->id,
                    'c1' => 'FLS, IN',
                ],
            ],
            $secclass->page(20, 0),
            'advs-secclass-page',
        );
    }

    public function testSearchUsersStaffnote(): void {
        $this->userList[0]->auditTrail()->addEvent(
            UserAuditEvent::staffNote, 'abcdef',
        );
        $this->userList[0]->auditTrail()->addEvent(
            UserAuditEvent::staffNote, 'ghijkl',
        );
        $staffnote = new Search\Users([
            'staffnote'    => 'abcdef or ghijkl',
            'staffnote-op' => 'webquery',
            'view'         => 'username.staffnote'
        ]);
        $this->assertSame(
            1,
            $staffnote->total(),
            'advs-staffnote-wq-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[0]->id,
                'c1' => ['ghijkl', 'abcdef'],
            ]],
            $staffnote->page(2, 0),
            'advs-staffnote-wq-page-result',
        );
        $staffnote = new Search\Users([
            'staffnote'    => '^.(bcd.f|hij.l)',
            'staffnote-op' => 'regexp',
            'view'         => 'username.staffnote'
        ]);
        $this->assertSame(
            1,
            $staffnote->total(),
            'advs-staffnote-re-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[0]->id,
                'c1' => ['ghijkl', 'abcdef'],
            ]],
            $staffnote->page(2, 0),
            'advs-staffnote-re-page-result',
        );
    }

    public function testSearchUsersStylesheet(): void {
        $kuro = array_first(array_filter(
            new Manager\Stylesheet()->list(),
            fn ($s) => $s['name'] === 'Kuro',
        ));
        $this->userList[0]->setField('stylesheet_id', $kuro['id'])->modify();
        $stylesheet = new Search\Users([
            'username'    => $this->userList[0]->username(),
            'username-op' => 'equal',
            'stylesheet'  => $kuro['id'],
            'view'        => 'username.stylesheet'
        ]);
        $this->assertSame(
            1,
            $stylesheet->total(),
            'advs-stylesheet-total-result',
        );
        $this->assertSame(
            [[
                'c0' => $this->userList[0]->id,
                'c1' => $kuro['name'],
            ]],
            $stylesheet->page(2, 0),
            'advs-stylesheet-page-result',
        );
    }

    public function testSearchUsersUserclass(): void {
        $user = array_first($this->userList);
        $userclass = new Search\Users([
            'email'     => $user->email(), // ensure we only match the test user
            'email-op'  => 'equal',
            'userclass' => [USER],
            'view'      => 'email.userclass'
        ]);
        $this->assertSame(
            1,
            $userclass->total(),
            'advs-userclass-total'
        );
        $this->assertSame(
            [[
                'c0' => $user->email(),
                'c1' => $user->userclassName(),
            ]],
            $userclass->page(2, 0),
            'advs-userclass-page',
        );
    }

    public function testSearchUsersUsernameEquals(): void {
        $this->userList[0]->setField('Username', 'abc')->modify();
        $search = new Search\Users([
            'username'    => 'abc',
            'username-op' => 'equals',
            'view'        => 'joined.email'
        ]);
        $this->assertSame(
            1,
            $search->total(),
            'advs-username-equals-total-result',
        );
        $this->assertEquals(
            [[
                'c0' => new \DateTimeImmutable($this->userList[0]->created()),
                'c1' => $this->userList[0]->email(),
            ]],
            $search->page(2, 0),
            'advs-username-equals-page-result',
        );
    }

    public function testSearchUsersWarned(): void {
        $user = array_first($this->userList);
        $user->warning()->add('phpunit', '1 hour', $user);
        $search = new Search\Users([
            'email'    => $user->email(),
            'email-op' => 'equal',
            'view'     => 'email.warned',
        ]);
        $this->assertSame(
            1,
            $search->total(),
            'advs-warned-total'
        );
        $this->assertEquals(
            [[
                'c0' => $user->email(),
                'c1' => $user->warning()->expiry(),
            ]],
            $search->page(2, 0),
            'advs-warned-page',
        );
        $this->assertSame(
            1,
            $search->total(),
            'advs-warn-total'
        );
        $warn = new Search\Users([
            'email'    => $user->email(),
            'email-op' => 'equal',
            'warned'   => 'on',
            'view'     => 'username',
        ]);
        $this->assertSame(
            [[
                'c0' => $user->id,
            ]],
            $warn->page(2, 0),
            'advs-warn-page',
        );
    }

    public function testSearchUsersDefaultHeading(): void {
        $search = new Search\Users([
            'inviter'    => 'abc',
            'inviter-op' => 'equals',
            'view'       => 'username.userid.email.joined.lastseen.userclass.secclass.enabled.disable.locked.inviter.invited.invsource.referral.siteip.trackerip.staffnote.bprate.bptotal.fltoken.uploads.tdownload.tdownload-uniq.leech.snatch.snatch-uniq.seedhour.seeding.requestcreate.uploadgb.downloadgb.requestvotegb.requestgaingb.warned'
        ]);
        $this->assertSame('Username', $search->heading()->alias('c0'), 'advs-heading-alias-c0');
        $this->assertSame('User ID', $search->heading()->alias('c1'), 'advs-heading-alias-c1');
        $this->assertSame('Email', $search->heading()->alias('c2'), 'advs-heading-alias-c2');
        $this->assertSame('Joined', $search->heading()->alias('c3'), 'advs-heading-alias-c3');
        $this->assertSame('Last seen', $search->heading()->alias('c4'), 'advs-heading-alias-c4');
        $this->assertSame('Userclass', $search->heading()->alias('c5'), 'advs-heading-alias-c5');
        $this->assertSame('Secondary', $search->heading()->alias('c6'), 'advs-heading-alias-c6');
        $this->assertSame('Enabled', $search->heading()->alias('c7'), 'advs-heading-alias-c7');
        $this->assertSame('Revoked?', $search->heading()->alias('c8'), 'advs-heading-alias-c8');
        $this->assertSame('Locked', $search->heading()->alias('c9'), 'advs-heading-alias-c9');
        $this->assertSame('Invited by', $search->heading()->alias('c10'), 'advs-heading-alias-c10');
        $this->assertSame('# Invited', $search->heading()->alias('c11'), 'advs-heading-alias-c11');
        $this->assertSame('Recruit site', $search->heading()->alias('c12'), 'advs-heading-alias-c12');
        $this->assertSame('Referral site', $search->heading()->alias('c13'), 'advs-heading-alias-c13');
        $this->assertSame('Site IP', $search->heading()->alias('c14'), 'advs-heading-alias-c14');
        $this->assertSame('Tracker IP', $search->heading()->alias('c15'), 'advs-heading-alias-c15');
        $this->assertSame('Staff note', $search->heading()->alias('c16'), 'advs-heading-alias-c16');
        $this->assertSame('BP/hour', $search->heading()->alias('c17'), 'advs-heading-alias-c17');
        $this->assertSame('BP total', $search->heading()->alias('c18'), 'advs-heading-alias-c18');
        $this->assertSame('FL tokens', $search->heading()->alias('c19'), 'advs-heading-alias-c19');
        $this->assertSame('# Uploads', $search->heading()->alias('c20'), 'advs-heading-alias-c20');
        $this->assertSame('Torrents DLed', $search->heading()->alias('c21'), 'advs-heading-alias-c21');
        $this->assertSame('Torrents DLed (Uniq)', $search->heading()->alias('c22'), 'advs-heading-alias-c22');
        $this->assertSame('Leeching', $search->heading()->alias('c23'), 'advs-heading-alias-c23');
        $this->assertSame('Snatched', $search->heading()->alias('c24'), 'advs-heading-alias-c24');
        $this->assertSame('Snatched (Uniq)', $search->heading()->alias('c25'), 'advs-heading-alias-c25');
        $this->assertSame('Seedtime (h)', $search->heading()->alias('c26'), 'advs-heading-alias-c26');
        $this->assertSame('Seeding', $search->heading()->alias('c27'), 'advs-heading-alias-c27');
        $this->assertSame('# Requests', $search->heading()->alias('c28'), 'advs-heading-alias-c28');
        $this->assertSame('Up (GiB)', $search->heading()->alias('c29'), 'advs-heading-alias-c29');
        $this->assertSame('Down (GiB)', $search->heading()->alias('c30'), 'advs-heading-alias-c30');
        $this->assertSame('Voted (GiB)', $search->heading()->alias('c31'), 'advs-heading-alias-c31');
        $this->assertSame('Bounty (GiB)', $search->heading()->alias('c32'), 'advs-heading-alias-c32');
    }

    public function testSearchUsersFiltered(): void {
        $unfiltered = new Search\Users([]);
        $this->assertFalse($unfiltered->isFiltered(), 'advs-unfiltered');
        $filtered = new Search\Users(['username' => '', 'username-op' => 'equals']);
        $this->assertTrue($filtered->isFiltered(), 'advs-filtered');
    }
}
