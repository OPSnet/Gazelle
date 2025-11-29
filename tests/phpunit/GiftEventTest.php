<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\GiftStatus;

class GiftEventTest extends TestCase {
    protected User $user;
    protected User $extra;

    public function setUp(): void {
        $this->user = Helper::makeUser('gift.' . randomString(6), 'gift', true);
    }

    public function tearDown(): void {
        if (isset($this->extra)) {
            $this->extra->remove();
        }
        $this->user->remove();
        new DB\Pg(PG_RW_DSN)->prepared_query('
            delete from gift_event where name ~ ?
            ', '^phpunit-'
        );
        new Manager\GiftEvent()->flush();
    }

    public function testGiftEventOpenClose(): void {
        $now       = microtime(true);
        $name      = 'phpunit-oc-' . randomString(10);
        $begin     = \DateTime::createFromFormat('U.u', $now)->format('Y-m-d H:i:s.u'); /** @phpstan-ignore-line stfu */
        $end       = \DateTime::createFromFormat('U.u', $now + 30)->format('Y-m-d H:i:s.u'); /** @phpstan-ignore-line stfu */
        $manager   = new Manager\GiftEvent();
        $giftEvent = $manager->create($name, $begin, $end, $this->user);

        $this->assertEquals(
            $giftEvent->id,
            $manager->findById($giftEvent->id)->id,
            'gift-event-find-by-id',
        );
        $this->assertEquals($this->user->id, $giftEvent->userId(), 'gift-event-creator');
        $this->assertEquals($name, $giftEvent->flush()->name(), 'gift-event-name-and-flush');
        $this->assertEquals(
            "<a href=\"tools.php?action=gift-event&amp;id={$giftEvent->id}\">{$name}</a>",
            $giftEvent->link(),
            'gift-event-link'
        );
        $this->assertEquals(
            "tools.php?action=gift-event&id={$giftEvent->id}",
            $giftEvent->location(),
            'gift-event-location'
        );
        $this->assertEquals(0.0, $giftEvent->averageWeight(), 'gift-event-avg-weight');
        $this->assertEqualsWithDelta(
            $now,
            (float)$giftEvent->openBegin()->format('U.u'),
            0.01,
            'gift-event-begin',
        );
        $this->assertEqualsWithDelta(
            $now + 30,
            (float)$giftEvent->openEnd()->format('U.u'),
            0.01,
            'gift-event-end',
        );

        $this->assertTrue($giftEvent->isOpen(), 'gift-event-open');
        $this->assertEquals(1, $giftEvent->close(), 'gift-event-close');
        $this->assertFalse($giftEvent->isOpen(), 'gift-event-is-closed');
        $this->assertEquals(0, $giftEvent->close(), 'gift-event-reclose');
        $this->assertEquals(1, $giftEvent->remove(), 'gift-event-remove');
    }

    public function testGiftEventAllocate(): void {
        $manager   = new Manager\GiftEvent();
        $now       = microtime(true);
        $giftEvent = $manager->create(
            'phpunit-sum-' . randomString(10),
            \DateTime::createFromFormat('U.u', $now)->format('Y-m-d H:i:s.u'), /** @phpstan-ignore-line stfu */
            \DateTime::createFromFormat('U.u', $now + 86400)->format('Y-m-d H:i:s.u'), /** @phpstan-ignore-line stfu */
            $this->user,
        );

        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            $giftEvent->signature($this->user),
            $this->user->id,
        );
        $this->assertEquals(GiftStatus::success, $status, 'gift-alloc-status-success');
        $this->assertInstanceOf(Gift::class, $gift, 'gift-alloc-gift-success');

        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            $giftEvent->signature($this->user),
            $this->user->id,
        );
        $this->assertEquals(GiftStatus::tooEarly, $status, 'gift-alloc-status-too-early');
        $this->assertNull($gift, 'gift-alloc-gift-too-early');

        $this->extra = Helper::makeUser('gift.' . randomString(6), 'gift', true);
        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            signature(implode('|', [$giftEvent->id, date('Y-m-d'), $this->extra->id + 1]), GIFT_SALT),
            $this->extra->id + 1,
        );
        $this->assertEquals(GiftStatus::badParameter, $status, 'gift-alloc-status-bad');
        $this->assertNull($gift, 'gift-alloc-gift-bad');

        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d', time() - 86400),
            $giftEvent->signature($this->extra),
            $this->user->id,
        );
        $this->assertEquals(GiftStatus::linkExpired, $status, 'gift-alloc-status-expired');
        $this->assertNull($gift, 'gift-alloc-gift-expired');

        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            'nope',
            $this->user->id,
        );
        $this->assertEquals(GiftStatus::invalidParameter, $status, 'gift-alloc-status-invalid');
        $this->assertNull($gift, 'gift-alloc-gift-invalid');

        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            $giftEvent->signature($this->user),
            $this->user->id + 1,
        );
        $this->assertEquals(GiftStatus::invalidParameter, $status, 'gift-alloc-status-u-invalid');
        $this->assertNull($gift, 'gift-alloc-gift-u-invalid');

        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            $giftEvent->signature($this->user),
            $this->user->id,
        );
        $this->assertEquals(GiftStatus::tooEarly, $status, 'gift-alloc-status-too-early');
        $this->assertNull($gift, 'gift-alloc-gift-too-early');

        $dayStats = $giftEvent->dayStats();
        $this->assertCount(1, $dayStats, 'gift-event-day-stats-count');
        $this->assertEquals(
            1,
            $dayStats[0]['total'],
            'gift-event-day-stats-total',
        );
        $this->assertEquals(
            $giftEvent->openBegin()->format('Y-m-d'),
            $dayStats[0]['day']->format('Y-m-d'),
            'gift-event-day-stats-day',
        );
        $this->assertEquals(
            [
                [
                    'activity' => 1,
                    'total'    => 1,
                ],
            ],
            $giftEvent->assiduityStats(),
            'gift-event-assuidity',
        );

        $giftEvent->close();
        [$status, $gift] = $giftEvent->allocateGift(
            date('Y-m-d'),
            $giftEvent->signature($this->user),
            $this->user->id,
        );
        $this->assertEquals(GiftStatus::eventClosed, $status, 'gift-alloc-status-closed');
        $this->assertNull($gift, 'gift-alloc-gift-closed');

        $giftEvent->remove();
    }

    public function testGiftEventSummary(): void {
        $manager   = new Manager\GiftEvent();
        $now       = microtime(true);
        $giftEvent = $manager->create(
            'phpunit-sum-' . randomString(10),
            \DateTime::createFromFormat('U.u', $now)->format('Y-m-d H:i:s.u'), /** @phpstan-ignore-line stfu */
            \DateTime::createFromFormat('U.u', $now + 30)->format('Y-m-d H:i:s.u'), /** @phpstan-ignore-line stfu */
            $this->user,
        );
        $eventList = $manager->eventList();
        $this->assertGreaterThan(0, count($eventList), 'gift-event-manager-summary');

        [$day, $hour, $minute] = explode(' ', date('Y-m-d H i'));
        $this->assertEquals(
            43,
            strlen($giftEvent->signature($this->user)),
            'gift-event-signature',
        );
        $giftEvent->allocateGift($day, $giftEvent->signature($this->user), $this->user->id);
        $current = array_filter(
            $eventList,
            fn ($e) => $e->id === $giftEvent->id
        );
        $this->assertCount(1, $current, 'gift-event-current');
        $this->assertEquals(1, $giftEvent->totalUser(), 'gift-event-total-user');
        $this->assertEquals(1, $giftEvent->totalGift(), 'gift-event-total-gift');

        $list = $giftEvent->userPage(10, 0, 'total', 'desc');
        $this->assertCount(1, $list, 'gift-event-user-page-total');
        $row = current($list);
        $this->assertEquals($this->user->id, $row['id_user'], 'gift-event-user-page-id');
        $this->assertEquals(1, $row['total'], 'gift-event-user-page-total');
        $this->assertEquals(
            ['id_user', 'username', 'total', 'first', 'last'],
            array_keys($row),
            'gift-event-user-page-key'
        );

        $giftList = $giftEvent->userGiftList($this->user);
        $this->assertCount(1, $giftList, 'gift-event-user-gift-total');
        $this->assertEquals(
            ['id_user_has_gift', 'id_gift', 'created', 'gift'],
            array_keys($giftList[0]),
            'gift-event-user-gift-key'
        );

        $statsList = $giftEvent->giftStats();
        $this->assertEquals(
            count($statsList),
            count(new Manager\Gift()->giftList()),
            'gift-event-gift-stats-list',
        );
        $stats = array_filter(
            $statsList,
            fn ($s) => $s['id_gift'] === $giftList[0]['id_gift']
        );
        $this->assertEquals(
            1,
            current($stats)['total'],
            'gift-event-gift-stats-item-total',
        );
        $giftEvent->remove();
    }
}
