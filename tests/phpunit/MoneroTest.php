<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class MoneroTest extends TestCase {
    public function testMoneroAddress(): void {
        $cn = new \MoneroIntegrations\MoneroPhp\Cryptonote();
        $mainAddress = "4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRj5UzqtReoS44qo9mtmXCqY45DJ852K5Jv2684Rge";
        $mainDecoded = $cn->decode_address($mainAddress);
        $m = new Donate\Monero($mainAddress);

        $user = Helper::makeUser('user.' . randomString(10), 'monero');
        $addr = $m->address($user->id);
        $addr2 = $m->address($user->id);

        $this->assertSame($addr, $addr2, 'monero-deterministic-address');

        $addrDecoded = $cn->decode_address($addr);

        $this->assertSame($mainDecoded['spendKey'], $addrDecoded['spendKey'], 'monero-verify-spendkey');
        $this->assertSame($mainDecoded['viewKey'], $addrDecoded['viewKey'], 'monero-verify-viewkey');
        $this->assertSame('13', $addrDecoded['networkByte'], 'monero-verify-network-byte');

        // man, this lib is very barebone; and why is it using hex everywhere?
        $decoded = new \MoneroIntegrations\MoneroPhp\base58()->decode($addr);
        $paymentId = substr($decoded, 64 + 66, 16);

        $this->assertSame($user->id, $m->findUserIdbyPaymentId($paymentId), 'monero-lookup-payment-id');

        $this->assertTrue($m->invalidate($user->id), 'monero-invalidate-success');
        $this->assertFalse($m->invalidate($user->id), 'monero-invalidate-fail');

        $this->assertNull($m->findUserIdbyPaymentId($paymentId), 'monero-lookup-payment-id-fail');

        $user->remove();
    }
}
