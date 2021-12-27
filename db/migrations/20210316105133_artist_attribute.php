<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ArtistAttribute extends AbstractMigration
{
    public function change(): void
    {
        $this->table('artist_attr', ['id' => false, 'primary_key' => 'artist_attr_id'])
             ->addColumn('artist_attr_id', 'integer', ['limit' => 6, 'identity' => true])
             ->addColumn('name', 'string', ['limit' => 24])
             ->addColumn('description', 'string', ['limit' => 500])
             ->addIndex(['name'], ['unique' => true])
             ->create();

        $this->table('artist_has_attr', ['id' => false, 'primary_key' => ['artist_id', 'artist_attr_id' ]])
            ->addColumn('artist_id', 'integer', ['limit' => 10])
            ->addColumn('artist_attr_id', 'integer', ['limit' => 6])
            ->addIndex(['artist_attr_id'])
            ->addForeignKey('artist_id', 'artists_group', 'ArtistID')
            ->addForeignKey('artist_attr_id', 'artist_attr', 'artist_attr_id')
            ->create();

        $this->table('artist_attr')
            ->insert([
                [
                    'name' => 'locked',
                    'Description' => 'This artist is locked, no alias modifications are permitted',
                ],
            ])
            ->save();
    }
}
