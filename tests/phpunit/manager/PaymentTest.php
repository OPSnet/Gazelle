<?php

namespace Gazelle\Manager;

use GazelleUnitTest\Helper;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase {
    public function tearDown(): void {
    }

    public function testPayment(): void {
        $manager = new Payment();
        $name    = 'phpunit-' . randomString(6);
        $expiry  = new \DateTimeImmutable("50 days")->modify('midnight');
        $id      = $manager->create(
            text:   $name,
            expiry: $expiry,
            rent:   0.03125,
            cc:     'XBT',
            active: true,
        );

        $this->assertGreaterThan(0, $id, 'payment-create');
        $payment = array_first(array_filter($manager->list(), fn ($p) => $p['id'] === $id));
        $this->assertTrue($payment['active'], 'payment-active');
        $this->assertSame($name, $payment['name'], 'payment-text');
        $this->assertEquals($expiry, $payment['expiry'], 'payment-expiry');
        $this->assertSame('XBT', $payment['currency'], 'payment-cc');
        $this->assertSame(0.03125, $payment['rent'], 'payment-rent');
        $this->assertSame(0.03125, $payment['annual_rent'], 'payment-btc-annual');
        $this->assertSame(0.03125, $payment['btcRent'], 'payment-btc-rent');
        $this->assertSame(1.0, $payment['fiatRate'], 'payment-fiat');

        $this->assertSame(1, $manager->remove($id), 'payment-remove');
    }

    public function testMonth(): void {
        $manager = new Payment();
        $id      = $manager->create(
            text:   'phpunit-' . randomString(6),
            expiry: new \DateTimeImmutable("120 days")->modify('midnight'),
            rent:   0.03125 * 12,
            cc:     'XBT',
            active: true,
        );

        // if these fails it is because there are other payment records or
        // donations in the database
        Helper::flushDonationMonth(1);
        $this->assertSame(
            0.03125,
            $manager->monthlyRental(),
            'payment-monthly-rental',
        );
        $this->assertSame(
            0,
            $manager->monthlyPercent(new Donation()),
            'payment-monthly-percent',
        );

        $manager->remove($id);
    }

    public function testSoon(): void {
        $manager = new Payment();
        $next    = new \DateTimeImmutable("3 days")->modify('midnight');
        $idList  =  [
            $manager->create(
                text:   'phpunit-one-' . randomString(6),
                expiry: $next,
                rent:   0.03125,
                cc:     'XBT',
                active: true,
            ),
            $manager->create(
                text:   'phpunit-two-' . randomString(6),
                expiry: new \DateTimeImmutable("4 days")->modify('midnight'),
                rent:   0.03125,
                cc:     'XBT',
                active: true,
            ),
        ];

        $soon = $manager->soon();
        $this->assertCount(2, $soon, 'payment-soon-element');
        $this->assertSame(2, $soon['total'], 'payment-soon-total');
        $this->assertEquals($next, $soon['next'], 'payment-soon-next');

        $this->assertSame(
            1,
            $manager->modify(
                id:     $idList[1],
                text:   'phpunit-' . randomString(6),
                expiry: new \DateTimeImmutable("4 days")->modify('midnight'),
                rent:   0.03125,
                cc:     'XBT',
                active: false,
            ),
            'payment-modify',
        );
        $soon = $manager->soon();
        $this->assertSame(1, $soon['total'], 'payment-sooner-total');

        foreach ($idList as $id) {
            $manager->remove($id);
        }
    }
}
