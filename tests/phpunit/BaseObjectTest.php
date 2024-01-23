<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class BaseObjectTest extends TestCase {
    protected \Gazelle\User $object;

    public function testBaseObject(): void {
        $object = Helper::makeUser('bo.' . randomString(6), 'base object');

        $this->assertFalse($object->dirty(), 'base-object-initial');
        $this->assertNull($object->field('phpunit'), 'base-object-no-field');
        $this->assertInstanceOf($object::class, $object->setField('phpunit', 'value'), 'base-object-set-field');
        $this->assertTrue($object->dirty(), 'base-object-dirty');
        $this->assertEquals('value', $object->field('phpunit'), 'base-object-has-field');

        $this->assertEquals('value', $object->clearField('phpunit'), 'base-object-clear-field');
        $this->assertFalse($object->dirty(), 'base-object-final');
        $this->assertNull($object->field('phpunit'), 'base-object-no-final');
        $object->remove();
    }
}
