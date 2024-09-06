<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class BitcoinTest extends TestCase {
    public function testBitcoinAddress(): void {
        $zpub = 'zpub6pxALoy3dUWEueNVAYHu71cc2WvLpAaAuya8A8ZKzFMZenahEXFFzQCJKeBW9ZA2Suh2vQV3UYoAL6rMN2bGdxXmS1SZ6Ku11jQBVAhjWuQ';
        $counter = (new Manager\Counter())
            ->create('testBAddr' . randomString(6), 'testBAddr' . randomString(6), -1);
        $b = new Donate\Bitcoin($zpub, $counter);

        $user = \GazelleUnitTest\Helper::makeUser('user.' . randomString(10), 'bitcoin');
        $addr = $b->address($user->id());
        $addr2 = $b->address($user->id());

        $this->assertEquals($addr, $addr2, 'bitcoin-deterministic-address');
        $this->assertEquals('bc1qseej7kpuldfjjmedq5ehkpd0jqadxsx4j0zkqk', $addr, 'bitcoin-verify-addr');

        $this->assertEquals($user->id(), $b->findUserIdbyAddress($addr), 'bitcoin-lookup-addr');
        $this->assertTrue($b->invalidate($user->id()), 'bitcoin-invalidate-success');
        $this->assertFalse($b->invalidate($user->id()), 'bitcoin-invalidate-fail');
        $this->assertNull($b->findUserIdbyAddress($addr), 'bitcoin-lookup-addr-fail');

        $newAddr = $b->address($user->id());
        $this->assertEquals('bc1qxpfasx86cav34eupzkxnjdsx7r9sn2490kepjx', $newAddr, 'bitcoin-verify-addr2');
        $this->assertTrue($b->invalidate($user->id()), 'bitcoin-invalidate-reinvalidate');

        $user->remove();
    }

    public function testBitcoinAddressXpub(): void {
        $xpub = 'xpub6D7NqpxWckGwCHhpXoL4pH38m5xVty62KY2wUh6JoyDCofwHciDRoQ3xm7WAg2ffpHaC6X4bEociYq81niyNUGhCxEs6fDFAd1LPbEmzcAm';
        $counter = (new Manager\Counter())
            ->create('addrXpub' . randomString(6), 'testAddrXpub' . randomString(6), -1);
        $b = new Donate\Bitcoin($xpub, $counter);

        $user = \GazelleUnitTest\Helper::makeUser('user.' . randomString(10), 'bitcoinxpub');
        $addr = $b->address($user->id());
        $this->assertEquals('1JdkxJzyGgUB9m77GsmRZExxLeizmxtQsq', $addr, 'bitcoin-verify-xpub');
        $this->assertTrue($b->invalidate($user->id()), 'bitcoin-invalidate-xpub'); // cleanup
        $user->remove();
    }

    public function testBitcoinAddressYpub(): void {
        $ypub = 'ypub6UesGZYeB5dLVoQPz9hn896uSSFSVV1Gz6mcDj8nyswqMo6nWnBeVqWL2TLSxEiXBiYzPcn6Y3eHf5ETa88JX6UeYpZQsm2uZmW4Mniv8eC';
        $counter = (new Manager\Counter())
            ->create('addrYpub' . randomString(6), 'testAddrYpub' . randomString(6), -1);
        $b = new Donate\Bitcoin($ypub, $counter);

        $user = \GazelleUnitTest\Helper::makeUser('user.' . randomString(10), 'bitcoinypub');
        $addr = $b->address($user->id());
        $this->assertEquals('3DYoBqQ5N6dADzyQjy9FT1Ls4amiYVaqTG', $addr, 'bitcoin-verify-ypub');
        $this->assertTrue($b->invalidate($user->id()), 'bitcoin-invalidate-ypub'); // cleanup
        $user->remove();
    }
}
