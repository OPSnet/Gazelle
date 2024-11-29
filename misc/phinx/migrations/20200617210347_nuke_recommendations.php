<?php

use Phinx\Migration\AbstractMigration;

class NukeRecommendations extends AbstractMigration {
    public function up(): void {
        $this->table('torrents_recommended')->drop()->update();
        $this->table('users_enable_recommendations')->drop()->update();
    }

    public function down(): void {
        $this->table('torrents_recommended', [
                'id' => false,
                'primary_key' => ['GroupID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('GroupID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('UserID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Time', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['Time'], [
                'name' => 'Time',
                'unique' => false,
            ])
            ->create();
        $this->table('users_enable_recommendations', [
                'id' => false,
                'primary_key' => ['ID'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'comment' => '',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('ID', 'integer', [
                'null' => false,
                'limit' => 10,
            ])
            ->addColumn('Enable', 'boolean', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['Enable'], [
                'name' => 'Enable',
                'unique' => false,
            ])
            ->create();
    }
}
