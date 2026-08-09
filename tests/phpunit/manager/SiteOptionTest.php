<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class SiteOptionTest extends TestCase {
    public function testCreateSiteOption(): void {
        $manager  = new Manager\SiteOption();
        $name     = 'phpunit-' . randomString(10);
        $value    = randomString();
        $comment  = "phpunit test";
        $optionId = $manager->createOption($name, $value, $comment);
        $this->assertGreaterThan(0, $optionId, 'site-option-create');
        $this->assertNull(
            $manager->createOption($name, $value, "phpunit test dupe"),
            'site-option-dupe',
        );
        // exercise the SQL
        global $Cache;
        $Cache->delete_value(sprintf($manager::CACHE_KEY, $name));
        $this->assertSame(
            $value,
            $manager->findValueByName($name),
            'site-option-find-by-name',
        );
        $list = $manager->list();
        $this->assertSame(
            [
                'id'      => $optionId,
                'name'    => $name,
                'value'   => $value,
                'comment' => $comment,
            ],
            $list[$name],
            'site-option-list',
        );
        $this->assertSame(
            1,
            $manager->modifyOption($name, "$value mod"),
            'site-option-modify',
        );
        $this->assertSame(
            "$value mod",
            $manager->findValueByName($name),
            'site-option-find-modified',
        );
        $this->assertSame(
            1,
            $manager->removeOptionByName($name),
            'site-option-remove',
        );
    }
}
