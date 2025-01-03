<?php

namespace Gazelle\Manager;

use GazelleUnitTest\Helper;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase {
    public function testPayment(): void {
        $manager = new Payment();
        $name    = 'phpunit-' . randomString(6);
        $expiry  = date('Y-m-d', strtotime("50 days"));
        $id      = $manager->create(
            text:   $name,
            expiry: $expiry,
            rent:   0.03125,
            cc:     'XBT',
            active: true,
        );

        $this->assertGreaterThan(0, $id, 'payment-create');
        $payment = current(array_filter($manager->list(), fn ($p) => $p['ID'] === $id));
        $this->assertTrue($payment['Active'], 'payment-active');
        $this->assertEquals($name, $payment['Text'], 'payment-text');
        $this->assertEquals($expiry, $payment['Expiry'], 'payment-expiry');
        $this->assertEquals('XBT', $payment['cc'], 'payment-cc');
        $this->assertEquals(0.03125, $payment['Rent'], 'payment-rent');
        $this->assertEquals(0.03125, $payment['AnnualRent'], 'payment-btc-annual');
        $this->assertEquals(0.03125, $payment['btcRent'], 'payment-btc-rent');
        $this->assertEquals(1, $payment['fiatRate'], 'payment-fiat');

        $this->assertEquals(1, $manager->remove($id), 'payment-remove');
    }

    public function testMonth(): void {
        $manager = new Payment();
        $id      = $manager->create(
            text:   'phpunit-' . randomString(6),
            expiry: date('Y-m-d', strtotime("120 days")),
            rent:   0.03125 * 12,
            cc:     'XBT',
            active: true,
        );

        // if these fails it is because there are other payment records or
        // donations in the database
        Helper::flushDonationMonth(1);
        $this->assertEquals(
            0.03125,
            $manager->monthlyRental(),
            'payment-monthly-rental',
        );
        $this->assertEquals(
            0,
            $manager->monthlyPercent(new Donation()),
            'payment-monthly-percent',
        );

        $manager->remove($id);
    }

    public function testSoon(): void {
        $manager = new Payment();
        $next    = date('Y-m-d', strtotime("3 days"));
        $idList  =  [
            $manager->create(
                text:   'phpunit-' . randomString(6),
                expiry: $next,
                rent:   0.03125,
                cc:     'XBT',
                active: true,
            ),
            $manager->create(
                text:   'phpunit-' . randomString(6),
                expiry: date('Y-m-d', strtotime("4 days")),
                rent:   0.03125,
                cc:     'XBT',
                active: true,
            ),
        ];

        $soon = $manager->soon();
        $this->assertCount(2, $soon, 'payment-soon-element');
        $this->assertEquals(2, $soon['total'], 'payment-soon-total');
        $this->assertEquals($next, $soon['next'], 'payment-soon-next');
        $this->assertCount(2, $manager->due(), 'payment-due');

        $this->assertEquals(
            1,
            $manager->modify(
                id:     $idList[1],
                text:   'phpunit-' . randomString(6),
                expiry: date('Y-m-d', strtotime("4 days")),
                rent:   0.03125,
                cc:     'XBT',
                active: false,
            ),
            'payment-modify',
        );
        $soon = $manager->soon();
        $this->assertEquals(1, $soon['total'], 'payment-sooner-total');

        foreach ($idList as $id) {
            $manager->remove($id);
        }
    }
}
