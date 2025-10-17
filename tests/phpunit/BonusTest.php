<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use GazelleUnitTest\Helper;
use Gazelle\Enum\BonusItemPurchaseStatus;
use Gazelle\Enum\UserStatus;

class BonusTest extends TestCase {
    protected array $userList;
    protected array $tgroupList;

    public function tearDown(): void {
        if (isset($this->tgroupList)) {
            foreach ($this->tgroupList as $tgroup) {
                Helper::removeTGroup($tgroup, current($this->userList));
            }
        }
        if (isset($this->userList)) {
            foreach ($this->userList as $user) {
                $user->remove();
            }
        }
    }

    public function testBonusBasic(): void {
        $this->userList['giver']    = Helper::makeUser('bonusg.' . randomString(6), 'bonus', true);
        $this->userList['receiver'] = Helper::makeUser('bonusr.' . randomString(6), 'bonus', true);
        $startingPoints = 10000;

        $user  = $this->userList['giver'];
        $giver = new User\Bonus($user);
        $this->assertEquals(0.0, $giver->hourlyRate(), 'bonus-per-hour');
        $this->assertEquals(0, $giver->user()->bonusPointsTotal(), 'bonus-points-initial');
        $this->assertEquals(0, $giver->user()->tokenCount(), 'bonus-fltokens-intial');
        $this->assertCount(0, $giver->history(10, 0), 'bonus-history-initial');
        $this->assertEquals(['nr' => 0, 'total' => 0], $giver->summary(), 'bonus-summary-initial');
        $this->assertEquals(
            [
                'total_torrents' => 0,
                'total_size'     => 0,
                'hourly_points'  => 0.0,
                'daily_points'   => 0.0,
                'weekly_points'  => 0.0,
                'monthly_points' => 0.0,
                'yearly_points'  => 0.0,
                'points_per_gb'  => 0.0,
                ],
            $giver->userTotals(),
            'bonus-accrual-initial'
        );

        $giver->setPoints($startingPoints);
        $this->assertEquals(
            $startingPoints,
            $giver->user()->bonusPointsTotal(),
            'bonus-set-points',
        );

        $manager = new Manager\Bonus();
        // Here is as good a place as any
        $this->assertEquals(0, $manager->discount(), 'bonus-discount');

        $manager->flush();
        $this->assertCount(13, $manager->itemList(), 'bonus-item-list');
        $this->assertNull($manager->findBonusItemByLabel('nope'), 'bonus-item-null');

        // buy a token
        $flt = $manager->findBonusItemByLabel('token-1');
        $this->assertFalse($flt->needsPreparation(), 'bonus-token-no-prepare');
        $this->assertInstanceOf(BonusItem::class, $flt->flush(), 'bonus-item-flush');
        $this->assertEquals('', $flt->link(), 'bonus-item-link');
        $this->assertEquals('bonus.php', $flt->location(), 'bonus-item-location');
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $flt->purchase($user, $flt->price()),
            'bonus-item-purchase-token-1',
        );
        $this->assertEquals(
            1,
            $giver->user()->tokenCount(),
            'bonus-fltokens-bought',
        );
        $this->assertEquals(
            $startingPoints - $flt->price(),
            $giver->user()->bonusPointsTotal(),
            'bonus-spent-points',
        );
        $history = $giver->history(10, 0);
        $this->assertEquals(
            '1 Freeleech Token',
            $history[0]['Title'],
            'bonus-history-title',
        );

        // buy a seedbox
        $seedbox = $manager->findBonusItemByLabel('seedbox');
        $this->assertEquals(
            'Seedbox Viewer',
            $seedbox->title(),
            'bonus-item-title',
        );
        $this->assertEquals(
            BonusItemPurchaseStatus::forbidden,
            $seedbox->purchase($user, $seedbox->price()),
            'bonus-item-purchase-seedbox-forbidden',
        );
        $user->setField('PermissionID', MEMBER)->modify();
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $seedbox->purchase($user, $seedbox->price()),
            'bonus-item-purchase-seedbox-success',
        );
        $this->assertTrue($giver->user()->hasAttr('feature-seedbox'), 'giver-has-seedbox');
        $this->assertCount(2, $giver->history(10, 0), 'bonus-history-new');
        $this->assertFalse($seedbox->isRecurring(), 'bonus-item-recurring');
        $this->assertEquals(
            BonusItemPurchaseStatus::alreadyPurchased,
            $seedbox->purchase($user, 0),
            'bonus-item-purchase-seedbox-duplicate',
        );

        // not enough point to buy a fifty
        $flt50 = $manager->findBonusItemByLabel('token-3');
        $this->assertEquals(
            BonusItemPurchaseStatus::insufficientFunds,
            $flt50->purchase($user, $flt50->price()),
            'bonus-item-purchase-token-50',
        );

        // buy file count feature
        $fileCount = $manager->findBonusItemByLabel('file-count');
        $giver->addPoints((float)$fileCount->price());
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $fileCount->purchase($user, $fileCount->price()),
            'bonus-item-purchase-file-count',
        );
        $this->assertTrue($user->hasAttr('feature-file-count'), 'giver-has-file-count');
        $this->assertEquals(
            BonusItemPurchaseStatus::alreadyPurchased,
            $fileCount->purchase($user, $fileCount->price()),
            'bonus-item-repurchase-file-count',
        );

        $this->assertEquals(
            $flt->price()
                + $seedbox->price()
                + $fileCount->price(),
            $giver->pointsSpent(),
            'bonus-points-spent',
        );

        $bbn = $manager->findBonusItemByLabel('title-bb-n');
        $giver->addPoints($bbn->price());
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $bbn->purchase($user, $bbn->price(), ['title' => '[b]i got u[/b]']),
            'bonus-item-title-no-bb',
        );
        $this->assertEquals('i got u', $user->title(), 'bonus-item-user-has-title-no-bb');

        $bby = $manager->findBonusItemByLabel('title-bb-y');
        $giver->addPoints($bby->price());
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $bby->purchase($user, $bby->price(), ['title' => '[b]i got u[/b]']),
            'bonus-item-title-yes-bb',
        );
        $this->assertEquals(
            '<strong>i got u</strong>',
            $user->title(),
            'bonus-item-user-has-title-yes-bb',
        );

        $off = $manager->findBonusItemByLabel('title-off');
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $off->purchase($user, $off->price()),
            'bonus-item-title-off',
        );
        $this->assertEquals(
            '',
            $user->title(),
            'bonus-item-user-has-no-title',
        );

        $collage = $manager->findBonusItemByLabel('collage-1');
        $giver->addPoints($collage->price());
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $collage->purchase($user, $collage->price()),
            'bonus-item-purchase-collage',
        );
        $this->assertEquals(
            $collage->price() * 2,
            $collage->priceForUser($giver->user()),
            'bonus-item-price-for-user'
        );
        $this->assertEquals(
            $collage->price(),
            $collage->priceForUser($this->userList['receiver']),
            'bonus-item-price-for-other-user'
        );

        $history = $giver->history(10, 0);
        $this->assertCount(7, $history, 'bonus-history-final');

        $this->assertEquals(
            [
                'nr' => 7,
                'total' => $flt->price()
                    + $seedbox->price()
                    + $fileCount->price()
                    + $collage->price()
                    + $bbn->price()
                    + $bby->price()
                    + $off->price()
            ],
            $giver->summary(),
            'bonus-summary-initial'
        );

        $history = $giver->purchaseHistory();
        $this->assertCount(7, $history, 'bonus-history-count');
        $this->assertEquals(
            ['id', 'title', 'total', 'cost'],
            array_keys(current($history)),
            'bonus-history-shape',
        );

        $this->assertTrue($giver->removePoints(1.125), 'bonus-taketh-away');
        $this->assertEquals(
            BonusItemPurchaseStatus::insufficientFunds,
            $bby->purchase($user, $bby->price(), ['title' => 'whatever']),
            'bonus-item-no-funds-title-yes-bb',
        );
        // higher userclasses get some items for free
        $user->setField('PermissionID', SYSOP)->modify();
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $bby->purchase($user, $bby->price(), ['title' => 'whatever']),
            'bonus-item-free-title-yes-bb',
        );
        $this->assertEquals(
            BonusItemPurchaseStatus::incomplete,
            $bby->purchase($user, $bby->price()),
            'bonus-item-free-title-yes-bb',
        );

        $this->assertCount(0, $giver->seedList(5, 0), 'bonus-history-seedlist');
        $this->assertCount(0, $giver->poolHistory(), 'bonus-history-pool');
    }

    public function testBonusPurchaseOther(): void {
        $this->userList['giver']    = Helper::makeUser('bonusg.' . randomString(6), 'bonus', true);
        $this->userList['receiver'] = Helper::makeUser('bonusr.' . randomString(6), 'bonus', true);

        $manager  = new Manager\Bonus();
        $other1   = $manager->findBonusItemByLabel('other-1');
        $other50  = $manager->findBonusItemByLabel('other-3');
        $user     = $this->userList['giver'];
        $receiver = $this->userList['receiver'];
        $giver    = new User\Bonus($user);

        $this->assertTrue($other1->needsPreparation(), 'bonus-token-other-prepare');
        $this->assertEquals(
            BonusItemPurchaseStatus::incomplete,
            $other50->purchase(
                $user,
                $other50->price(),
            ),
            'bonus-item-purchase-incomplete-other-50',
        );

        $giver->addPoints((float)$other50->price());
        $receiver->toggleAttr('no-fl-gifts', true);
        $this->assertEquals(
            BonusItemPurchaseStatus::declined,
            $other50->purchase(
                $user,
                $other50->price(),
                [
                    'message'  => 'phpunit gift',
                    'receiver' => $receiver,
                ],
            ),
            'bonus-item-purchase-declined-other-50',
        );
        $receiver->toggleAttr('no-fl-gifts', false);
        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $other50->purchase(
                $user,
                $other50->price(),
                [
                    'message'  => 'phpunit gift',
                    'receiver' => $receiver,
                ],
            ),
            'bonus-item-purchase-other-50',
        );

        $this->assertEquals(
            BonusItemPurchaseStatus::insufficientFunds,
            $manager->purchaseTokenOther(
                $user,
                $receiver,
                'other-1',
                '',
            ),
            'bonus-purchase-token-other-no-money',
        );
        $this->assertCount(0,
            $manager->offerTokenOther($user, $receiver),
            'bonus-offer-other-none',
        );

        $giver->addPoints((float)$other1->price());
        $offer = $manager->offerTokenOther($user, $receiver);
        $this->assertEquals('other-1', $offer[0]->label(), 'bonus-item-all-I-can-give');

        $this->assertEquals(
            BonusItemPurchaseStatus::incomplete,
            $manager->purchaseTokenOther(
                $user,
                $receiver,
                'bad-label',
                '',
            ),
            'bonus-purchase-token-other-fail',
        );

        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $manager->purchaseTokenOther(
                $user,
                $receiver,
                'other-1',
                '',
            ),
            'bonus-purchase-token-other-success',
        );

        $this->assertEquals(
            BonusItemPurchaseStatus::success,
            $other1->purchase(
                $receiver,
                0,
                ['receiver' => $user],
            ),
            'bonus-item-receiver-give'
        );
        $this->assertEquals(
            [
                "received" => 51,
                "sent"     =>  1,
            ],
            (new User\Bonus($receiver)->tokenExchange()),
            'bonus-token-exchange',
        );
        $latest = $giver->otherLatest($receiver);
        $this->assertEquals(
            '50 Freeleech Tokens to Other',
            $latest['title'],
            'bonus-item-given'
        );

        $giver->addPoints((float)$other50->price() * 2);
        $this->assertCount(
            3,
            $manager->offerTokenOther($user, $receiver),
            'bonus-offer-other-all',
        );

        $this->assertFalse(
            $manager->findBonusItemByLabel('invite')
                ->priceForTokenOther($user, $receiver),
            'bonus-price-other-false',
        );
        $basePrice = $other50->priceForTokenOther($user, $receiver);
        $this->assertNotFalse($basePrice, 'bonus-price-token-other-valid');
        $other50->purchase(
            $user,
            $other50->price(),
            [
                'message'  => 'phpunit gift 2',
                'receiver' => $receiver,
            ],
        );
        $this->assertEquals(
            $basePrice * (1 + BONUS_OTHER_TOKEN_SCALE / 100),
            $other50->priceForTokenOther($user, $receiver),
            'bonus-price-other-increase',
        );
        $receiver->setField('inviter_user_id', $user->id)->modify();
        $this->assertEquals(
            $basePrice,
            $other50->priceForTokenOther($user, $receiver),
            'bonus-price-other-inviter',
        );
    }

    public static function providerBonusItem(): array {
        return [
            ['collage-1'],
            ['file-count'],
            ['invite'],
            ['seedbox'],
            ['other-1'],
            ['other-2'],
            ['other-3'],
            ['token-1'],
            ['token-2'],
            ['token-3'],
        ];
    }

    #[DataProvider('providerBonusItem')]
    public function testBonusBroke(string $label): void {
        $this->userList = [Helper::makeUser('bonusg.' . randomString(6), 'bonus', true)];
        $this->userList[0]->setField('PermissionID', MEMBER)->modify();
        $item = new Manager\Bonus()->findBonusItemByLabel($label);
        if (str_starts_with($label, 'other-')) {
            $this->userList[] = Helper::makeUser('bonusg.' . randomString(6), 'bonus', true);
            $this->assertEquals(
                BonusItemPurchaseStatus::insufficientFunds,
                $item->purchase(
                    $this->userList[0],
                    $item->price(),
                    ['receiver' => $this->userList[1]]
                ),
                "bonus-item-broke-$label",
            );
        } else {
            $this->assertEquals(
                BonusItemPurchaseStatus::insufficientFunds,
                $item->purchase($this->userList[0], $item->price()),
                "bonus-item-broke-$label",
            );
        }
    }

    public function testBonusPool(): void {
        global $Cache;
        $Cache->delete_value("bonus_pool");
        $this->assertEquals(
            [],
            new Manager\Bonus()->openPoolList(),
            'bonus-open-pool',
        );
    }

    public function testBonusInvite(): void {
        $user = Helper::makeUser('bonusinvite.' . randomString(6), 'bonus');
        $this->userList = [$user];

        $invite = new Manager\Bonus()->findBonusItemByLabel('invite');
        $this->assertTrue($invite->permitted($user), 'bonus-invite-yes');
        $user->toggleAttr('disable-invites', true);
        $this->assertFalse($invite->permitted($user), 'bonus-invite-no');

        $userBonus = new User\Bonus($user);
        $this->assertTrue(
            $userBonus->removePoints(1000, force: true),
            'bonus-remove-force'
        );
        $this->assertEquals(
            -1000,
            $user->bonusPointsTotal(),
            'bonus-points-negative'
        );
    }

    public function testBonusTitle(): void {
        $this->assertEquals(
            [],
            new Json\BonusItemTitle('nope', 'title')->payload(),
            'bonus-title-bad',
        );
        $this->assertEquals(
            ['phpunit'],
            new Json\BonusItemTitle('title-bb-n', '[b]phpunit[/b]')->payload(),
            'bonus-title-plain',
        );
        $this->assertEquals(
            ['<strong>phpunit</strong>'],
            new Json\BonusItemTitle('title-bb-y', '[b]phpunit[/b]')->payload(),
            'bonus-title-rich',
        );
    }

    public function testBonusUserOther(): void {
        $giver = Helper::makeUser('bonusother.' . randomString(6), 'bonus', enable: false);
        $receiver = Helper::makeUser('bonusother.' . randomString(6), 'bonus', enable: false);
        $this->userList = [$giver, $receiver];
        $token = new Manager\Bonus()->findBonusItemByLabel('other-1');

        $this->assertEquals(
            [
                'found'    => false,
                'username' => '#nope',
            ],
            new Json\BonusUserOther($giver, $token, '#nope')->payload(),
            'bonus-user-other-404',
        );
        $this->assertEqualsCanonicalizing(
            [
                'accept'   => true,
                'enabled'  => false,
                'found'    => true,
                'id'       => $receiver->id,
                'percent5' => 0,
                'price'    => 2500,
                'username' => $receiver->username(),
            ],
            new Json\BonusUserOther($giver, $token, $receiver->username())->payload(),
            'bonus-user-other-not-enabled',
        );
        $receiver->setField('Enabled', UserStatus::enabled->value)->modify();
        $this->assertEqualsCanonicalizing(
            [
                'accept'   => true,
                'enabled'  => true,
                'found'    => true,
                'id'       => $receiver->id,
                'percent5' => 0,
                'price'    => 2500,
                'username' => $receiver->username(),
            ],
            new Json\BonusUserOther($giver, $token, $receiver->username())->payload(),
            'bonus-user-other-accept',
        );
        $receiver->toggleAttr('no-fl-gifts', true);
        $this->assertEqualsCanonicalizing(
            [
                'accept'   => false,
                'enabled'  => true,
                'found'    => true,
                'id'       => $receiver->id,
                'percent5' => 0,
                'price'    => 2500,
                'username' => $receiver->username(),
            ],
            new Json\BonusUserOther($giver, $token, $receiver->username())->payload(),
            'bonus-user-other-no-fl',
        );
    }

    public function testAddPoints(): void {
        $this->userList = [
            Helper::makeUser('bonusadd.' . randomString(6), 'bonus', enable: false),
            Helper::makeUser('bonusadd.' . randomString(6), 'bonus', enable: true),
        ];
        // back to the future
        DB::DB()->prepared_query("
            INSERT INTO user_last_access (user_id, last_access) values (?, ?)
            ", $this->userList[1]->id, date('Y-m-d H:i:s', time() + 10)
        );

        $manager = new Manager\Bonus();
        $this->assertEquals(
            1,
            // but not too far
            $manager->addActivePoints(23456, date('Y-m-d H:i:s', time() + 5)),
            'bonus-add-active',
        );
        $this->assertEquals(
            0,
            $manager->addMultiPoints(789, []),
            'bonus-add-no-multi-points',
        );
        $this->assertEquals(
            2,
            $manager->addMultiPoints(
                12345,
                array_map(fn ($u) => $u->id, $this->userList),
            ),
            'bonus-add-multi-points',
        );
        $this->assertEquals(
            0,
            $manager->addUploadPoints(369,  date('Y-m-d H:i:s', time() + 1)),
            'bonus-add-upload-points',
        );
        $this->assertEquals(
            12345,
            $this->userList[0]->flush()->bonusPointsTotal(),
            'bonus-added-user-0',
        );

        $this->tgroupList[] =  Helper::makeTGroupMusic(
            name:       'bonus add ' . randomString(10),
            artistName: [[ARTIST_MAIN], ['phpunit bonus add ' . randomString(12)]],
            tagName:    ['hard.bop'],
            user:       $this->userList[1],
        );
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroupList[0],
            user:  $this->userList[1],
            title: 'phpunit bonus add ' . randomString(10),
        );
        Helper::generateTorrentSeed($torrent, $this->userList[1]);
        $this->assertGreaterThan(
            0,
            $manager->addSeedPoints(24680),
            'bonus-add-seed-points',
        );
        $this->assertEquals(
            12345 + 23456 + 24680,
            $this->userList[1]->flush()->bonusPointsTotal(),
            'bonus-added-user-1',
        );

        $this->userList[1]->toggleAttr('no-fl-gifts', true);
        $this->assertGreaterThan(
            0,
            $manager->addGlobalPoints(7531),
            'bonus-add-global-points',
        );
        $this->assertEquals(
            12345 + 23456 + 24680,
            $this->userList[1]->flush()->bonusPointsTotal(),
            'bonus-added-no-global',
        );
        foreach ($this->userList as $u) {
            new User\Bonus($u)->setPoints(0.0);
        }
    }

    public function testUploadReward(): void {
        $this->userList[] = Helper::makeUser('bonusup.' . randomString(6), 'bonus');
        $this->tgroupList[] =  Helper::makeTGroupMusic(
            name:       'bonus ' . randomString(10),
            artistName: [[ARTIST_MAIN], ['phpunit bonus ' . randomString(12)]],
            tagName:    ['bop'],
            user:       $this->userList[0],
        );
        $reward = new BonusUploadReward();

        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroupList[0],
            user:  $this->userList[0],
            title: 'phpunit bonus ' . randomString(10),
            media: 'Vinyl',
            format: 'FLAC',
            encoding: '24bit Lossless',
        );
        $this->assertEquals(400, $reward->reward($torrent), 'bonus-reward-perfect-flac');

        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroupList[0],
            user:  $this->userList[0],
            title: 'phpunit bonus ' . randomString(10),
            media: 'CD',
            format: 'FLAC',
            encoding: 'Lossless',
        );
        $this->assertEquals(30, $reward->reward($torrent), 'bonus-reward-flac');

        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroupList[0],
            user:  $this->userList[0],
            title: 'phpunit bonus ' . randomString(10),
            media: 'Vinyl',
            format: 'MP3',
            encoding: '320',
        );
        $this->assertEquals(30, $reward->reward($torrent), 'bonus-reward-mp3');

        $this->tgroupList[] =  Helper::makeTGroupEBook('phpunit ebook title');
        $torrent = Helper::makeTorrentEBook(
            tgroup: $this->tgroupList[1],
            user:  $this->userList[0],
            description: 'phpunit bonus ebook ' . randomString(10),
        );
        // If the following test fails, look in bonus_upload_reward with:
        // select * from bonus_upload_reward where id_category = 3 order by lower(valid);
        // You may have to close out the current valid range with something like
        // update bonus_upload_reward set valid = tstzrange(lower(valid), now()) where now() <@ valid and id_category = 3;
        // insert into bonus_upload_reward (id_category, high, standard, low, valid) values (3, 10, 10, 10, tstzrange(now(), 'infinity'));
        $this->assertEquals(10, $reward->reward($torrent), 'bonus-reward-other');

        $this->assertGreaterThan(
            0,
            $reward->modifyCategory(
                CATEGORY_EBOOK,
                ['high' => 330, 'standard' => 220, 'low' => 110],
            ),
            'bonus-reward-modify'
        );
        $torrent->setField('created', date('Y-m-d H:i:s', time() + 1))->modify();
        $this->assertEquals(220, $reward->reward($torrent->flush()), 'bonus-reward-new-other');
        $reward->modifyCategory(
            CATEGORY_EBOOK,
            ['high' => 10, 'standard' => 10, 'low' => 10],
        );
    }

    public function testUploadBoost(): void {
        $this->userList[] = $user = Helper::makeUser('bonusboost.' . randomString(6), 'bonus');
        $reward = new BonusUploadReward();

        $user->setField('PermissionID', ELITE)->modify();
        $this->assertEquals(0, $reward->boost($user), 'bonus-boost-elite');
        $user->setField('PermissionID', MEMBER)->modify();
        $this->assertEquals(BONUS_UPLOAD_BOOST[0], $reward->boost($user), 'bonus-boost-01');
        $this->assertEquals(BONUS_UPLOAD_BOOST[1], $reward->boost($user), 'bonus-boost-02');
        $this->assertEquals(BONUS_UPLOAD_BOOST[2], $reward->boost($user), 'bonus-boost-03');
        $this->assertEquals(BONUS_UPLOAD_BOOST[3], $reward->boost($user), 'bonus-boost-04');
        $this->assertEquals(BONUS_UPLOAD_BOOST[4], $reward->boost($user), 'bonus-boost-05');
        $this->assertEquals(0,                     $reward->boost($user), 'bonus-boost-06');
    }

    public function testStats(): void {
        $eco = new Stats\Economic();
        $eco->flush();

        $total    = $eco->bonusTotal();
        $stranded = $eco->bonusStrandedTotal();

        $this->userList['bonus'] = Helper::makeUser('bonusstat.' . randomString(6), 'bonus', true);
        $bonus = new User\Bonus($this->userList['bonus']);
        $bonus->addPoints(98765);

        $eco->flush();
        $this->assertEquals(98765 + $total, $eco->bonusTotal(), 'bonus-total-points');
        $this->assertEquals($stranded, $eco->bonusStrandedTotal(), 'bonus-total-stranded-points');

        $this->userList['bonus']->setField('Enabled', UserStatus::disabled->value)->modify();
        $eco->flush();
        $this->assertEquals(98765 + $stranded, $eco->bonusStrandedTotal(), 'bonus-total-disabled-stranded-points');
    }
}
