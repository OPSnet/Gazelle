<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class CounterTest extends TestCase {
    public function testCounter(): void {
        $name    = "phpunit-" . randomString(10);
        $manager = new Manager\Counter();
        $counter = $manager->create($name, 'phpunit description');

        $this->assertSame($name, $counter->name(), 'counter-name');
        $this->assertSame('phpunit description', $counter->description(), 'counter-description');
        $this->assertSame(0, $counter->value(), 'counter-initial-value');
        $this->assertSame(1, $counter->increment(), 'counter-increment');
        $this->assertSame(1, $counter->value(), 'counter-final-increment');

        $clone = $manager->find($counter->name());
        $this->assertInstanceOf(Counter::class, $clone, 'counter-found');
        $this->assertSame($name, $clone->name(), 'counter-find');
        $this->assertSame(2, $clone->increment(), 'counter-clone-increment');

        // need to flush if two are slugging it out
        $this->assertNotEquals(2, $counter->value(), 'counter-not-in-synch');
        $this->assertSame(2, $counter->flush()->value(), 'counter-in-synch');
    }
}
