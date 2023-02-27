<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

define('TEST_NAME', 'phpunit_bitvec_' . randomString(20));
define('TEST_LENGTH', 4); // bits 0..31
define('TEST_EXPIRY', 3600);

class CacheVectorTest extends TestCase {
    public function testCacheVector() {
        $bitvec = new Gazelle\Util\CacheVector(TEST_NAME, TEST_LENGTH, TEST_EXPIRY);
        $this->assertInstanceOf(Gazelle\Util\CacheVector::class, $bitvec, 'bitvec-ctor');
        $this->assertTrue($bitvec->isEmpty(), 'bitvec-new-empty');

        $truth = [1, 5, 8, 20, 27];
        $set = $bitvec->init(0, $truth);
        $this->assertEquals($set, count($truth), 'bitvec-init');
        $this->assertFalse($bitvec->isEmpty(), 'bitvec-not-empty');

        $this->assertTrue($bitvec->get(8), 'bitvec-get-8');
        $this->assertTrue($bitvec->get(1), 'bitvec-get-1');

        $this->assertFalse($bitvec->get(0), 'bitvec-get-0');
        $this->assertFalse($bitvec->get(31), 'bitvec-get-31');

        $this->assertFalse($bitvec->get(17), 'bitvec-get-17-f');
        $this->assertTrue($bitvec->set(17), 'bitvec-set-17');
        $this->assertTrue($bitvec->get(17), 'bitvec-get-17-t');

        $this->assertFalse($bitvec->get(100), 'bitvec-get-out-of-range');
        $this->assertFalse($bitvec->set(100), 'bitvec-set-out-of-range');
        $bitvec->persist();
        $this->assertTrue($bitvec->set(4), 'bitvec-set-4');

        $new = new Gazelle\Util\CacheVector(TEST_NAME, TEST_LENGTH, TEST_EXPIRY);
        $this->assertTrue($new->get(8), 'new-get-8');
        $this->assertTrue($new->get(17), 'new-get-17');
        $this->assertFalse($new->get(4), 'new-get-4');

        $bitvec->flush();
        $this->assertFalse($bitvec->get(8), 'bitvec-flush');
    }
}
