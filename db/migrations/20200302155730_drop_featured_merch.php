<?php

use Phinx\Migration\AbstractMigration;

class DropFeaturedMerch extends AbstractMigration
{
    public function up() {
        $this->table('featured_merch')->drop()->update();
    }

    public function down() {
        $this->table('featured_merch', [
                'id' => false,
            ])
            ->addColumn('ProductID', 'integer', [
                'default' => '0',
                'limit' => '10',
            ])
            ->addColumn('Title', 'string', [
                'default' => '',
                'limit' => 35,
            ])
            ->addColumn('Image', 'string', [
                'default' => '',
                'limit' => 255,
            ])
            ->addColumn('Started', 'datetime', [
                'default' => '0000-00-00 00:00:00',
            ])
            ->addColumn('Ended', 'datetime', [
                'default' => '0000-00-00 00:00:00',
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => '10',
                'signed' => false,
            ])
            ->create();
    }
}
