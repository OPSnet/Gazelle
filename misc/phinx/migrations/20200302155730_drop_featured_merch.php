<?php

use Phinx\Migration\AbstractMigration;

class DropFeaturedMerch extends AbstractMigration
{
    public function up(): void {
        $this->table('featured_merch')->drop()->update();
    }

    public function down(): void {
        $this->table('featured_merch', [
                'id' => false,
            ])
            ->addColumn('ProductID', 'integer', [
                'default' => '0',
                'limit' => 10,
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
                'null' => true
            ])
            ->addColumn('Ended', 'datetime', [
                'null' => true
            ])
            ->addColumn('ArtistID', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->create();
    }
}
