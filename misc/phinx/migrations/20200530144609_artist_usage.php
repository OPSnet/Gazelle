<?php

use Phinx\Migration\AbstractMigration;

class ArtistUsage extends AbstractMigration
{
    public function up(): void {
        $this->table('artist_usage', ['id' => false, 'primary_key' => ['artist_id', 'role']])
             ->addColumn('artist_id', 'integer', ['limit' => 10])
             ->addColumn('role', 'enum', ['values' => ['0', '1', '2', '3', '4', '5', '6', '7']])
             ->addColumn('uses', 'integer', ['limit' => 10, 'signed' => false])
             ->addForeignKey('artist_id', 'artists_group', 'ArtistID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();

        $this->table('periodic_task')
             ->insert([[
                 'name' => 'Artist Usage',
                 'classname' => 'ArtistUsage',
                 'description' => 'Calculates usage of artists',
                 'period' => 60 * 60 * 24,
             ]])
             ->save();
    }

    public function down(): void {
        $this->table('artist_usage')
             ->drop()
             ->save();

        $builder = $this->getQueryBuilder();
        $builder->delete('periodic_task')
                ->where(['classname' => 'ArtistUsage'])
                ->execute();
    }
}
