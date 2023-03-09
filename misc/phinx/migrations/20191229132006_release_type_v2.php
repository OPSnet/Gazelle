<?php


use Phinx\Migration\AbstractMigration;

class ReleaseTypeV2 extends AbstractMigration {
    public function change(): void {
        $this->table('release_type')->insert([
            ['ID' => 17, 'Name' => 'DJ Mix'],
            ['ID' => 18, 'Name' => 'Concert recording']
        ])->save();
    }
}
