<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class DonorTest extends TestCase {
    protected User\Donor $donor;

    public function setUp(): void {
        $this->donor = new User\Donor(
            Helper::makeUser('donor.' . randomString(6), 'donor', clearInbox: true)
        );
    }

    public function tearDown(): void {
        $user = $this->donor->user();
        $this->donor->remove();
        $user->remove();
    }

    public function testDonorCreate(): void {
        $donor = $this->donor;

        // before being a donor
        $this->assertFalse($donor->isDonor(), 'donor-is-new');
        $this->assertNull($donor->expirationDate(), 'donor-new-no-expiry');
        $this->assertNull($donor->lastDonationDate(), 'donor-new-no-last-donation');
        $this->assertFalse($donor->profileInfo(1), 'donor-new-no-profile-1');
        $this->assertFalse($donor->profileInfo(2), 'donor-new-no-profile-2');
        $this->assertFalse($donor->profileInfo(3), 'donor-new-no-profile-3');
        $this->assertFalse($donor->profileInfo(4), 'donor-new-no-profile-4');
        $this->assertSame('', $donor->heart($donor->user()), 'donor-new-no-heart');
        $this->assertSame(0, $donor->collageTotal(), 'donor-new-no-collage');

        // secondary class
        $this->assertSame(0, $donor->removeDonorStatus(), 'donor-new-remove-status');
        $this->assertSame(1, $donor->addDonorStatus(), 'donor-new-add-status');
        $this->assertSame(1, $donor->removeDonorStatus(), 'donor-new-clear-status');

        // test body
        $body = $donor->messageBody('EUR', 12.34, 1, 1);
        $this->assertStringContainsString('[b]You Contributed:[/b] 12.34 EUR', $body, 'donor-msg-amount');
        $this->assertStringContainsString('[b]You Received:[/b] 1 Donor Point', $body, 'donor-msg-point');
        $this->assertStringContainsString('[b]Your Donor Rank:[/b] Donor Rank # 1', $body, 'donor-msg-rank');

        // small donation
        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE / 2,
                xbtRate: 0.06125,
                source: 'phpunit source',
                reason: 'phpunit reason',
            ),
            'donor-small'
        );
        $this->assertFalse($donor->isDonor(), 'donor-is-now-new');
        $this->assertSame(0, $donor->invitesReceived(), 'donor-no-invites');

        $inbox = $donor->user()->inbox();
        $this->assertSame(1, $inbox->messageTotal(), 'donor-inbox-small-total');
        $list = $inbox->messageList(new Manager\PM($donor->user()), 1, 0);
        $this->assertStringContainsString('Your contribution has been received and credited', $list[0]->subject(), 'inbox-pm-subject');
        Helper::clearInbox($donor->user());

        // second donation
        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE,
                xbtRate: 0.06125,
                source: 'phpunit second source',
                reason: 'phpunit second reason',
            ),
            'donor-2nd-point'
        );
        $inbox->messageList(new Manager\PM($donor->user()), 2, 0);
        $this->assertSame(1, $donor->rank(), 'donor-rank-1');
        $this->assertSame(1, $donor->totalRank(), 'donor-total-rank-1');
        $this->assertSame(0, $donor->specialRank(), 'donor-not-special-rank-1');
        $this->assertSame(2, $donor->invitesReceived(), 'donor-received-2-invites');
        $this->assertSame('1 [Red]', $donor->rankLabel(), 'donor-rank-label-1');
        $this->assertSame(1, $donor->collageTotal(), 'donor-collage-1');
        $this->assertSame('Donor', $donor->iconHoverText(), 'donor-icon-hover-text-1');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor.png', $donor->heartIcon(), 'donor-heart-icon-1');
        $this->assertGreaterThan(0, $donor->leaderboardRank(), 'donor-leaderboard-rank-1');

        $this->assertFalse($donor->avatarHover(), 'donor-avatar-hover-1');
        $this->assertFalse($donor->avatarHoverText(), 'donor-avatar-hover-text-1');
        $this->assertFalse($donor->profileInfo(1), 'donor-profile1-info-1');
        $this->assertFalse($donor->profileTitle(1), 'donor-profile1-title-1');
        $this->assertFalse($donor->hasForum(), 'donor-has-forum-1');

        $this->assertTrue($donor->isVisible(), 'donor-is-visible');
        $this->assertFalse($donor->setVisible(false), 'donor-is-now-invisible');
        $this->assertFalse($donor->isVisible(), 'donor-is-hidden');
        $this->assertTrue($donor->setVisible(true), 'donor-is-now-visible');
        $this->assertTrue($donor->isVisible(), 'donor-is-not-hidden');
    }

    public function testDonorAdjust(): void {
        $donor = $this->donor;
        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE * 0.75,
                xbtRate: 0.06125,
                source: 'phpunit adjust source',
                reason: 'phpunit adjust reason',
            ),
            'donor-donate-rank-2'
        );
        $this->assertFalse($donor->isDonor(), 'donor-adj-is-donor');
        $this->assertSame(0, $donor->rank(), 'donor-adj-no-rank');
        $this->assertSame(0, $donor->totalRank(), 'donor-adj-no-total-rank');
        $this->assertSame(1, $donor->adjust(7, 12, 'phpunit adjust 1', $donor->user()), 'donor-adj-rank-plus');
        $this->assertSame(7, $donor->rank(), 'donor-adj-1-rank');
        $this->assertSame(12, $donor->totalRank(), 'donor-adj-1-total-rank');
        $this->assertSame(1, $donor->adjust(-4, -10, 'phpunit adjust 2', $donor->user()), 'donor-adj-rank-minus');
        $this->assertSame(3, $donor->rank(), 'donor-adj-2-rank');
        $this->assertSame(2, $donor->totalRank(), 'donor-adj-2-total-rank');

        $this->assertSame(0, $donor->specialRank(), 'donor-adj-no-special-rank');
        $this->assertSame(1, $donor->setSpecialRank(1), 'donor-set-special-1');
        $this->assertSame(3, $donor->setSpecialRank(3), 'donor-set-special-3');
        $this->assertTrue($donor->hasMaxSpecialRank(), 'donor-set-has-max-special');
    }

    public function testDonorAvatar(): void {
        $donor = $this->donor;
        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE * 50,
                xbtRate: 0.06125,
                source: 'phpunit avatar source',
                reason: 'phpunit avatar reason',
            ),
            'donor-donate-rank-2'
        );
        $this->assertTrue(
            $donor->updateAvatarHoverText('avatar hover')
                ->updateAvatarHover('https://www.example.com/donor.jpg')
                ->modify(),
            'donor-avatar-set'
        );
        $this->assertSame('', $donor->user()->avatar(), 'donor-avatar-blank');
        $this->assertSame(
            [
                "image" => USER_DEFAULT_AVATAR,
                "hover" => "https://www.example.com/donor.jpg",
                "text"  => "avatar hover",
            ],
            $donor->user()->avatarComponentList($donor->user()),
            'donor-avatar-component'
        );
    }

    public function testDonorStaff(): void {
        $donor = $this->donor;
        $donor->user()->setField('PermissionID', MOD)->modify();
        $this->assertTrue($donor->hasForum(), 'donor-mod-has-forum');
        $this->assertTrue($donor->hasRankAbove(0), 'donor-mod-has-rank');
        $this->assertTrue($donor->hasMaxSpecialRank(), 'donor-mod-has-max-special');
        $this->assertSame('Never', $donor->rankExpiry(), 'donor-mod-rank-expiry');
        $this->assertSame('∞ [Diamond]', $donor->rankLabel(), 'donor-mod-rank-label');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor_6.png', $donor->heartIcon(), 'donor-mod-heart-icon');
        $this->assertNull($donor->profileInfo(1), 'donor-mod-profile1-info');
        $this->assertNull($donor->profileTitle(1), 'donor-mod-profile1-title');
        $this->assertNull($donor->profileInfo(2), 'donor-mod-profile2-info');
        $this->assertNull($donor->profileTitle(2), 'donor-mod-profile2-title');
        $this->assertNull($donor->profileInfo(3), 'donor-mod-profile3-info');
        $this->assertNull($donor->profileTitle(3), 'donor-mod-profile3-title');
        $this->assertNull($donor->profileInfo(4), 'donor-mod-profile4-info');
        $this->assertNull($donor->profileTitle(4), 'donor-mod-profile4-title');
    }

    public function testDonorRank(): void {
        $donor = $this->donor;
        $source = 'phpunit rank 2 source';
        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE * 2,
                xbtRate: 0.06125,
                source: $source,
                reason: 'phpunit rank 2 reason',
            ),
            'donor-donate-rank-2'
        );

        $this->assertSame(1, $donor->user()->inbox()->messageTotal(), 'donor-inbox-rank-2');
        $this->assertSame(2, $donor->rank(), 'donor-rank-2');
        $this->assertSame(2, $donor->totalRank(), 'donor-total-rank-2');
        $this->assertSame(0, $donor->specialRank(), 'donor-not-special-rank-2');
        $this->assertSame('2 [Copper]', $donor->rankLabel(), 'donor-rank-label-2');
        $this->assertSame(2, $donor->invitesReceived(), 'donor-received-2-for-2-invites');
        $this->assertSame(2, $donor->collageTotal(), 'donor-collage-2');
        $this->assertStringContainsString('1 month', $donor->rankExpiry(), 'donor-rank-expiry-2');
        $this->assertSame('donate.php', $donor->iconLink(), 'donor-icon-link-2');
        $this->assertSame('donate.php', $donor->iconLink(), 'donor-icon-link-2');
        $this->assertSame('Donor', $donor->iconHoverText(), 'donor-icon-hover-text-2');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor_2.png', $donor->heartIcon(), 'donor-heart-icon-2');
        $this->assertTrue($donor->hasForum(), 'donor-has-forum-2');

        $this->assertTrue($donor->hasRankAbove(1), 'donor-has-above-rank1-2');
        $donor->updateProfileInfo(1, 'phpunit donor info 1');
        $this->assertFalse($donor->user()->dirty(), 'donor-user-clean-2');
        $this->assertTrue($donor->dirty(), 'donor-needs-update-2');
        $this->assertTrue($donor->modify(), 'donor-update-profile1-info-2');
        $this->assertSame('phpunit donor info 1', $donor->profileInfo(1), 'donor-profile1-info-2');
        $this->assertFalse($donor->profileInfo(2), 'donor-profile2-info-initial-2');
        $this->assertFalse($donor->hasRankAbove(2), 'donor-has-above-rank2-2');
        $this->assertFalse($donor->updateProfileInfo(2, 'phpunit donor info 2')->modify(), 'donor-no-profile2-info-2');
        $this->assertTrue($donor->updateIconHoverText('hover')->modify(), 'donor-update-icon-hover-2');
        $this->assertSame('hover', $donor->iconHoverText(), 'donor-icon-hover-2');

        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE,
                xbtRate: 0.06125,
                source: 'phpunit rank 3 source',
                reason: 'phpunit rank 3 reason',
            ),
            'donor-donate-rank-3'
        );
        $this->assertSame(3, $donor->rank(), 'donor-rank-3');
        $this->assertSame(3, $donor->totalRank(), 'donor-total-rank-3');
        $this->assertSame(0, $donor->specialRank(), 'donor-not-special-rank-3');
        $this->assertSame(3, $donor->collageTotal(), 'donor-collage-3');
        $this->assertSame('3 [Bronze]', $donor->rankLabel(), 'donor-rank-label-2');
        $this->assertSame(4, $donor->invitesReceived(), 'donor-received-3-for-4-invites');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor_3.png', $donor->heartIcon(), 'donor-heart-icon-3');
        $this->assertTrue($donor->updateProfileInfo(2, 'phpunit donor info 2')->modify(), 'donor-profile2-info-2');
        $this->assertSame('', $donor->avatarHoverText(), 'donor-no-avatar-hover-text-2');
        $this->assertTrue($donor->updateAvatarHoverText('avatar hover')->modify(), 'donor-update-avatar-hover-2');
        $this->assertSame('avatar hover', $donor->avatarHoverText(), 'donor-no-avatar-hover-text-2');

        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE,
                xbtRate: 0.06125,
                source: 'phpunit rank 4 source',
                reason: 'phpunit rank 4 reason',
            ),
            'donor-donate-rank-4'
        );
        $this->assertSame(4, $donor->rank(), 'donor-rank-4');
        $this->assertSame(4, $donor->totalRank(), 'donor-total-rank-4');
        $this->assertSame(0, $donor->specialRank(), 'donor-not-special-rank-4');
        $this->assertSame(4, $donor->collageTotal(), 'donor-collage-4');
        $this->assertSame('4 [Silver]', $donor->rankLabel(), 'donor-rank-label-2');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor_4.png', $donor->heartIcon(), 'donor-heart-icon-4');
        $this->assertTrue($donor->updateProfileInfo(3, 'phpunit donor info 3')->modify(), 'donor-profile3-info-2');
        $this->assertTrue($donor->updateIconLink('https://example.com/')->modify(), 'donor-update-icon-link-2');
        $this->assertSame('https://example.com/', $donor->iconLink(), 'donor-icon-link-2');

        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE,
                xbtRate: 0.06125,
                source: 'phpunit rank 5 source',
                reason: 'phpunit rank 5 reason',
            ),
            'donor-donate-rank-5'
        );
        $this->assertSame(5, $donor->rank(), 'donor-rank-5');
        $this->assertSame(5, $donor->totalRank(), 'donor-total-rank-5');
        $this->assertSame(0, $donor->specialRank(), 'donor-not-special-rank-5');
        $this->assertSame(5, $donor->collageTotal(), 'donor-collage-5');
        $this->assertSame('4 [Silver]', $donor->rankLabel(), 'donor-rank-label-4');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor_4.png', $donor->heartIcon(), 'donor-heart-icon-4');

        $this->assertFalse($donor->forumUseComma(), 'donor-has-forum-comma');
        $this->assertTrue($donor->setForumDecoration('The', 'Person', false), 'donor-set-forum-decoration');
        $this->assertSame('The', $donor->forumPrefix(), 'donor-has-forum-prefix');
        $this->assertSame('Person', $donor->forumSuffix(), 'donor-has-forum-suffix');
        $this->assertSame('Person', $donor->forumSuffix(), 'donor-has-forum-suffix');
        $this->assertSame("The {$donor->user()->username()} Person", $donor->username(decorated: true), 'donor-has-forum-comma-username');
        $this->assertTrue($donor->setForumDecoration('The', 'Person', true), 'donor-set-forum-comma');
        $this->assertTrue($donor->forumUseComma(), 'donor-has-forum-comma');
        $this->assertSame("The {$donor->user()->username()}, Person", $donor->username(decorated: true), 'donor-has-forum-comma-username');
        $this->assertSame($donor->user()->username(), $donor->username(decorated: false), 'donor-bare-username');

        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE,
                xbtRate: 0.06125,
                source: 'phpunit rank 6 source',
                reason: 'phpunit rank 6 reason',
            ),
            'donor-donate-rank-6'
        );
        $this->assertSame(6, $donor->rank(), 'donor-rank-6');
        $this->assertSame(6, $donor->totalRank(), 'donor-total-rank-6');
        $this->assertSame(0, $donor->specialRank(), 'donor-not-special-rank-5');
        $this->assertSame(5, $donor->collageTotal(), 'donor-collage-6-is-5');
        $this->assertSame('5 [Gold]', $donor->rankLabel(), 'donor-rank-label-5');
        $this->assertSame(STATIC_SERVER . '/common/symbols/donor_5.png', $donor->heartIcon(), 'donor-heart-icon-5');
        $this->assertCount(5, $donor->historyList(), 'donor-history-list');
        $this->assertFalse($donor->hasDonorPick(), 'donor-has-no-donor-pick');
        $this->assertFalse($donor->avatarHover(), 'donor-has-no-avatar-hover');
        $this->assertFalse($donor->updateAvatarHover('https://www.example.com/example.jpg')->modify(), 'donor-no-set-avatar-hover');

        // expire a donation
        $db = DB::DB();
        $db->prepared_query("
            UPDATE users_donor_ranks SET
                RankExpirationTime = now() - INTERVAL 767 HOUR
            WHERE UserID = (SELECT UserID FROM donations WHERE source = ?)
            ", $source
        );
        $this->assertSame(1, $db->affected_rows(), 'donor-found-expirable');

        $manager = new Manager\Donation();
        $this->assertSame(1, $manager->expireRanks(), 'donor-expiry');
        $donor->flush();
        $this->assertSame(5, $donor->rank(), 'donor-rank-now-5');
        $this->assertSame(6, $donor->totalRank(), 'donor-total-rank-still-6');
    }

    public function testDonorSpecial(): void {
        $donor = $this->donor;
        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE * 10,
                xbtRate: 0.06125,
                source: 'phpunit special 1 source',
                reason: 'phpunit special 1 reason',
            ),
            'donor-donate-special-1'
        );
        $this->assertSame(1, $donor->specialRank(), 'donor-special-rank-1');
        $this->assertTrue($donor->hasDonorPick(), 'donor-has-donor-pick');
        $this->assertSame(5, $donor->collageTotal(), 'donor-collage-special-1');

        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE * 10,
                xbtRate: 0.06125,
                source: 'phpunit special 2 source',
                reason: 'phpunit special 2 reason',
            ),
            'donor-donate-special-2'
        );
        $this->assertSame(2, $donor->specialRank(), 'donor-special-rank-2');
        $this->assertFalse($donor->avatarHover(), 'donor-has-empty-avatar-hover');
        $this->assertTrue($donor->updateAvatarHover('https://www.example.com/example.jpg')->modify(), 'donor-set-avatar-hover');
        $this->assertSame('https://www.example.com/example.jpg', $donor->avatarHover(), 'donor-has-avatar-hover');

        $this->assertSame(
            1,
            $donor->donate(
                amount: DONOR_RANK_PRICE * 30,
                xbtRate: 0.06125,
                source: 'phpunit special 3 source',
                reason: 'phpunit special 3 reason',
            ),
            'donor-donate-special-3'
        );
        $this->assertSame(3, $donor->specialRank(), 'donor-special-rank-3');
        $this->assertTrue($donor->hasMaxSpecialRank(), 'donor-has-max-special-rank');
        $this->assertSame('∞ [Diamond]', $donor->rankLabel(), 'donor-rank-label-infin');
        $this->assertSame('Never', $donor->rankExpiry(), 'donor-rank-expire-never');

        $this->assertTrue($donor->updateProfileTitle(1, 'phpunit donor title 1')->modify(), 'donor-info-title-1');
        $this->assertTrue($donor->updateProfileTitle(2, 'phpunit donor title 2')->modify(), 'donor-info-title-2');
        $this->assertTrue($donor->updateProfileTitle(3, 'phpunit donor title 3')->modify(), 'donor-info-title-3');
        $this->assertTrue($donor->updateProfileTitle(4, 'phpunit donor title 4')->modify(), 'donor-info-title-4');
        $this->assertSame('phpunit donor title 1', $donor->profileTitle(1), 'donor-infin-title-1');
        $this->assertSame('phpunit donor title 2', $donor->profileTitle(2), 'donor-infin-title-2');
        $this->assertSame('phpunit donor title 3', $donor->profileTitle(3), 'donor-infin-title-3');
        $this->assertSame('phpunit donor title 4', $donor->profileTitle(4), 'donor-infin-title-4');
        $this->assertSame('', $donor->profileInfo(1), 'donor-has-infin-profile-1');
        $this->assertSame('', $donor->profileInfo(2), 'donor-has-infin-profile-2');
        $this->assertSame('', $donor->profileInfo(3), 'donor-has-infin-profile-3');
        $this->assertSame('', $donor->profileInfo(4), 'donor-has-infin-profile-4');
    }

    public function testDonorManager(): void {
        $manager = new Manager\Donation();
        $userMan = new Manager\User();
        $initial = $manager->rewardTotal();
        $initialGrand = $manager->grandTotal();

        $this->donor->donate(
            amount: DONOR_RANK_PRICE * 2.75,
            xbtRate: 0.06125,
            source: 'phpunit rank 2a source',
            reason: 'phpunit rank 2a reason',
        );
        $this->assertSame($initial + 1, $manager->rewardTotal(), 'donor-manager-reward-total');

        $this->donor->donate(
            amount: DONOR_RANK_PRICE * 3.75,
            xbtRate: 0.06125,
            source: 'phpunit rank 2b source',
            reason: 'phpunit rank 2b reason',
        );
        $this->assertSame(
            number_format($initialGrand + DONOR_RANK_PRICE * 6.5 / 0.06125, 4),
            number_format($manager->grandTotal(), 4),
            'donor-manager-grand-total'
        );

        Helper::flushDonationMonth(1); // can be required when testing locally
        $this->assertGreaterThan(0, $manager->topDonorList(100, $userMan), 'donor-top-donor');
        $this->assertGreaterThan($initial + DONOR_RANK_PRICE, $manager->totalMonth(1), 'donor-manager-month');
        $username = $this->donor->user()->username();
        $entry = array_first(array_filter(
            $manager->rewardPage(null, 100, 0),
            fn($d) => $d['user_id'] == $this->donor->user()->id,
        ));
        $this->assertSame($username, $entry['Username'], 'donor-manager-reward-username');
        $entry = array_first(array_filter(
            $manager->rewardPage($username, 100, 0),
            fn($d) => $d['user_id'] == $this->donor->user()->id,
        ));
        $this->assertSame($username, $entry['Username'], 'donor-manager-reward-search');

        $timeline = $manager->timeline();
        $last = array_last($timeline);

        Helper::flushDonationMonth(1);
        $this->assertLessThanOrEqual($manager->totalMonth(1), $last['Amount'], 'donor-manager-timeline');

        $paginator = new Util\Paginator(USERS_PER_PAGE, 1)->setTotal($manager->rewardTotal());
        Base::staticRequestContext()->setViewer($this->donor->user());
        Util\Twig::setViewer($this->donor->user());
        Base::staticRequestContext()->setSessionId('phpunit');
        $render = (Util\Twig::factory($userMan))->render('donation/reward-list.twig', [
            'paginator' => $paginator,
            'user'      => $manager->rewardPage(null, $paginator->limit(), $paginator->offset()),
            'search'    => null,
        ]);
        $this->assertStringContainsString($this->donor->link(), $render, 'donor-manager-reward-page-render');
    }

    public function testDonorTwig(): void {
        $user = $this->donor->user();
        $twig = Util\Twig::factory(new Manager\User());
        $template = $twig->createTemplate('{% if user is donor %}yes{% else %}no{% endif %}');
        $this->assertSame('no', $template->render(['user' => $user]), 'twig-test-not-donor');

        $this->donor->donate(
            amount: DONOR_RANK_PRICE,
            xbtRate: 0.06125,
            source: 'phpunit donor source',
            reason: 'phpunit donor reason',
        );
        $this->assertSame('yes', $template->render(['user' => $user]), 'twig-test-not-donor');

        $panel = $twig->render('donation/admin-panel.twig', [
            'donor' => $this->donor,
        ]);
        $this->assertStringStartsWith('<table class="layout" id="donation_box">', $panel, 'twig-test-admin-donor-begin');
        $this->assertStringEndsWith("</table>\n", $panel, 'twig-test-admin-donor-end');
    }
}
