<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class CounterTest extends TestCase {
    public function testCounter(): void {
        $name    = "phpunit-" . randomString(10);
        $manager = new Manager\Counter();
        $counter = $manager->create($name, 'phpunit description');

        $this->assertEquals($name, $counter->name(), 'counter-name');
        $this->assertEquals('phpunit description', $counter->description(), 'counter-description');
        $this->assertEquals(0, $counter->value(), 'counter-initial-value');
        $this->assertEquals(1, $counter->increment(), 'counter-increment');
        $this->assertEquals(1, $counter->value(), 'counter-final-increment');

        $clone = $manager->find($counter->name());
        $this->assertEquals($name, $clone->name(), 'counter-find');
        $this->assertEquals(2, $clone->increment(), 'counter-clone-increment');

        // need to flush if two are slugging it out
        $this->assertNotEquals(2, $counter->value(), 'counter-not-in-synch');
        $this->assertEquals(2, $counter->flush()->value(), 'counter-in-synch');
    }
}
