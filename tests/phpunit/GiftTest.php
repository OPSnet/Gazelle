<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class GiftTest extends TestCase {
    protected User $user;
    protected Request $request;

    public function setUp(): void {
        $this->user = Helper::makeUser('gift.' . randomString(6), 'gift', true);
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            $this->request->remove();
        }
        $this->user->remove();
    }

    public function testGiftUp(): void {
        $manager = new Manager\Gift();
        $this->assertGreaterThan(
            0,
            $manager->weightTotal(),
            'gift-manager-weight-total',
        );
        $this->assertNull(
            $manager->findByLabel('does-not-exist'),
            'gift-not-by-name'
        );

        $gift = $manager->flush()->findByLabel('up-1');
        $this->assertInstanceOf(
            Gift::class,
            $gift,
            'gift-by-name'
        );
        $this->assertEquals(
            'Add 1 GiB to your upload stats',
            $gift->flush()->description(),
            'gift-description'
        );
        $this->assertEquals(
            '',
            $gift->link(),
            'gift-link'
        );
        $this->assertEquals(
            '',
            $gift->location(),
            'gift-location'
        );
        $initial = $manager->giftTotal();
        $gift->giveTo($this->user);
        $this->assertEquals(
            STARTING_UPLOAD + $gift->factor() * 1024 ** 3,
            $this->user->uploadedSize(),
            'gift-up-1',
        );
        $this->assertEquals($initial + 1, $manager->giftTotal(), 'gift-manager-given-total');
        $this->assertEquals(
            $gift->id,
            $manager->findById($gift->id)->id,
            'gift-man-find-by-id',
        );
    }

    public function testGiftDown(): void {
        $manager = new Manager\Gift();
        $manager->flush();
        $this->user->setField('leech_download', 2 * 1024 ** 3)->modify(); // 2 GiB down

        $manager->findByLabel('down-1')->giveTo($this->user);
        $this->assertEquals(
            1024 ** 3,
            $this->user->downloadedSize(),
            'gift-down-1',
        );

        // overflow download to upload
        // had 1 down: gifted 5 down => 0 down, +4 up
        $manager->findByLabel('down-5')->giveTo($this->user);
        $this->assertEquals(
            0,
            $this->user->downloadedSize(),
            'gift-down-5-down',
        );
        $this->assertEquals(
            STARTING_UPLOAD + 4 * 1024 ** 3,
            $this->user->uploadedSize(),
            'gift-down-5-up',
        );
    }

    public function testGiftBonus(): void {
        $manager = new Manager\Gift();
        $manager->flush();
        $gift = $manager->findByLabel('bonus-10000');
        $gift->giveTo($this->user);
        $this->assertEquals(
            $gift->factor(),
            $this->user->bonusPointsTotal(),
            'gift-bonus',
        );
    }

    public function testGiftCollage(): void {
        new Manager\Gift()->findByLabel('collage')->giveTo($this->user);
        $this->assertEquals(
            1,
            $this->user->paidPersonalCollages(),
            'gift-collage',
        );
    }

    public function testGiftRequestCreate(): void {
        $this->request = Helper::makeRequestMusic($this->user, 'phpunit gift request create');
        $initial       = $this->request->bountyTotal();
        $gift          = new Manager\Gift()->findByLabel('reqc-100');
        $gift->giveTo($this->user);
        $this->assertEquals(
            $gift->factor() * 1024 ** 3,
            $this->request->flush()->bountyTotal() - $initial,
            'gift-request-create',
        );
    }

    public function testGiftRequestVote(): void {
        $this->request = Helper::makeRequestMusic($this->user, 'phpunit gift request vote');
        $this->request->vote($this->user, 100 * 1024 * 1024);
        $initial = $this->request->bountyTotal();
        $gift    = new Manager\Gift()->findByLabel('reqv-100');
        $gift->giveTo($this->user);
        $this->assertEquals(
            $gift->factor() * 1024 ** 3,
            $this->request->flush()->bountyTotal() - $initial,
            'gift-request-vote',
        );
    }

    public function testGiftToken(): void {
        new Manager\Gift()->findByLabel('token-200')->giveTo($this->user);
        $this->assertEquals(
            200,
            $this->user->tokenCount(),
            'gift-token',
        );
    }

    public function testGiftWeight(): void {
        $manager = new Manager\Gift();
        // if this fails, try again
        $manager->findByLabel('coal')->setTemporaryWeight(2 ** 32 - 1000000);
        $this->assertEquals(
            'coal',
            $manager->choose()->label(),
            'gift-choose',
        );
    }
}
