<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class ReportAutoTypeTest extends TestCase {
    protected Manager\ReportAutoType $ratMan;

    public function setUp(): void {
        $this->ratMan = new Manager\ReportAutoType();
    }

    public function testfindByNull(): void {
        $this->assertNull($this->ratMan->findById(1337), 'ratman-find-id-null');
        $this->assertNull($this->ratMan->findByName('doesnotexist'), 'ratman-find-name-null');
    }

    public function testCreate(): void {
        $type = $this->ratMan->create('type name', 'useful description');
        $this->assertSame('type name', $type->name(), 'ratman-create-nocat2');

        $catId = $this->ratMan->createCategory('somecategory');
        $this->assertGreaterThan(0, $catId, 'ratman-create-cat-valid');

        $type2 = $this->ratMan->create('type2 name', 'useful description', 'somecategory');
        $this->assertSame($catId, $type2->categoryId(), 'ratman-create-cat2');

        $type3 = $this->ratMan->create('type3 name', 'useful description', 'newcat');
        $this->assertSame('newcat', $type3->category(), 'ratman-create-newcat2');

        $type4 = $this->ratMan->create('type4 name', 'useful description', 'newcat');
        $this->assertSame('newcat', $type4->category(), 'ratman-create-newcat4');
        $this->assertSame($type3->categoryId(), $type4->categoryId(), 'ratman-create-newcat5');

        $type4_n = $this->ratMan->findById($type4->id);
        $this->assertInstanceOf(ReportAuto\Type::class, $type4_n, 'ratman-find-instance-n');
        $this->assertEquals($type4, $type4_n, 'ratman-find-id');
        $type4_nn = $this->ratMan->findByName($type4->name());
        $this->assertInstanceOf(ReportAuto\Type::class, $type4_nn, 'ratman-find-instance-nn');
        $this->assertEquals($type4, $type4_nn, 'ratman-find-name');
    }
}
