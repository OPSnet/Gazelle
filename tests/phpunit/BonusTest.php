<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class BonusTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testBonus(): void {
        $creator = new Gazelle\UserCreator;
        $this->userList['giver'] = $creator
            ->setUsername('bonusg.' . randomString(6))
            ->setEmail(randomString(6) . "@bonus.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setAdminComment('Created by tests/phpunit/InviteTest.php')
            ->create();
        $this->userList['receiver'] = $creator
            ->setUsername('bonusr.' . randomString(6))
            ->setEmail(randomString(6) . "@bonus.example.com")
            ->setPassword(randomString())
            ->setIpaddr('127.0.0.1')
            ->setAdminComment('Created by tests/phpunit/InviteTest.php')
            ->create();

        $this->userList['giver']->setField('Enabled', '1')->modify();
        $this->userList['receiver']->setField('Enabled', '1')->modify();
        $startingPoints = 10000;

        $giver = new Gazelle\User\Bonus($this->userList['giver']);
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
        $this->assertEquals($startingPoints, $giver->user()->bonusPointsTotal(), 'bonus-set-points');

        $itemList = (new Gazelle\Manager\Bonus)->itemList();
        $this->assertArrayHasKey('token-1', $itemList, 'item-token-1');
        $token = $giver->item('token-1');
        $price = $token['Price'];
        $this->assertEquals($price, $giver->effectivePrice('token-1'), 'item-price-token-1');

        // buy a token
        $this->assertTrue($giver->purchaseToken('token-1'), 'item-purchase-token-1');
        $this->assertEquals(1, $giver->user()->tokenCount(), 'bonus-fltokens-bought');
        $this->assertEquals($startingPoints - $price, $giver->user()->bonusPointsTotal(), 'bonus-spent-points');
        $history = $giver->history(10, 0);
        $this->assertEquals('1 Freeleech Token', $history[0]['Title'], 'bonus-history-title');

        // buy a seedbox
        $this->assertTrue($giver->unlockSeedbox(), 'item-purchase-seedbox');
        $this->assertTrue($giver->user()->hasAttr('feature-seedbox'), 'giver-has-seedbox');
        $this->assertCount(2, $giver->history(10, 0), 'bonus-history-new');

        // not enough point to buy a fifty
        $this->assertFalse($giver->purchaseToken('token-50'), 'item-purchase-token-50');

        $giver->addPoints($giver->item('other-1')['Price'] + $giver->item('other-3')['Price']);
        $this->assertEquals(
            $giver->item('other-3')['Amount'],
            $giver->purchaseTokenOther($this->userList['receiver'], 'other-3', 'phpunit gift'),
            'item-purchase-other-50'
        );
        $other = $giver->otherList();
        $this->assertEquals('other-1', $other[0]['Label'], 'item-all-I-can-give');

        $this->assertEquals(
            $giver->item('token-1')['Price'] + $giver->item('other-3')['Price'] + $giver->item('seedbox')['Price'],
            $giver->pointsSpent(),
            'bonus-points-spent'
        );

        $latest = $giver->otherLatest($this->userList['receiver']);
        $this->assertEquals('50 Freeleech Tokens to Other', $latest['title'], 'item-given');

        $giver->addPoints($giver->item('title-bb-n')['Price']);
        $this->assertTrue($giver->purchaseTitle('title-bb-n', '[b]i got u[/b]'), 'item-title-no-bb');
        $this->assertEquals('i got u', $giver->user()->title(), 'item-user-has-title-no-bb');

        $giver->addPoints($giver->item('title-bb-y')['Price']);
        $this->assertTrue($giver->purchaseTitle('title-bb-y', '[b]i got u[/b]'), 'item-title-yes-bb');
        $this->assertEquals('<strong>i got u</strong>', $giver->user()->title(), 'item-user-has-title-yes-bb');

        $giver->addPoints($giver->item('collage-1')['Price']);
        $this->assertTrue($giver->purchaseCollage('collage-1'), 'item-purchase-collage');

        $history = $giver->history(10, 0);
        $this->assertCount(6, $history, 'bonus-history-final');

        $this->assertEquals(
            [
                'nr' => 6,
                'total' => $giver->item('token-1')['Price']
                    + $giver->item('other-3')['Price']
                    + $giver->item('seedbox')['Price']
                    + $giver->item('collage-1')['Price']
                    + $giver->item('title-bb-y')['Price']
                    + $giver->item('title-bb-n')['Price']
            ],
            $giver->summary(),
            'bonus-summary-initial'
        );
        $this->assertTrue($giver->removePoints(1.125), 'bonus-taketh-away');
    }
}
