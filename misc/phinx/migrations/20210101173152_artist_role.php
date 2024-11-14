<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class ArtistRole extends AbstractMigration
{
    public function change(): void {
        $this->table('artist_role', ['id' => false, 'primary_key' => ['artist_role_id']])
            ->addColumn('artist_role_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('sequence', 'integer')
            ->addColumn('slug', 'string', ['limit' => 12])
            ->addColumn('name', 'string', ['limit' => 20])
            ->addColumn('title', 'string', ['limit' => 20])
            ->addColumn('collection', 'string', ['limit' => 20, 'null' => true])
            ->addIndex(['slug'], ['name' => 'ar_slug_idx', 'unique' => true])
            ->create();

        $this->table('artist_role')
            ->insert([
                [
                    'slug' => 'main',
                    'name' => 'Main',
                    'title' => 'Main',
                    'collection' => null,
                    'sequence' => 1,
                ], [
                    'slug' => 'guest',
                    'name' => 'Guest',
                    'title' => 'With',
                    'collection' => null,
                    'sequence' => 1024,
                ], [
                    'slug' => 'remixer',
                    'name' => 'Remixer',
                    'title' => 'Remixed by',
                    'collection' => 'Remixes',
                    'sequence' => 1023,
                ], [
                    'slug' => 'composer',
                    'name' => 'Composer',
                    'title' => 'Composers',
                    'collection' => 'Compositions',
                    'sequence' => 1022,
                ], [
                    'slug' => 'conductor',
                    'name' => 'Conductor',
                    'title' => 'Conducted by',
                    'collection' => null,
                    'sequence' => 5,
                ], [
                    'slug' => 'dj',
                    'name' => 'DJ',
                    'title' => 'DJ / Compiler',
                    'collection' => null,
                    'sequence' => 6,
                ], [
                    'slug' => 'producer',
                    'name' => 'Producer',
                    'title' => 'Producted by',
                    'collection' => null,
                    'sequence' => 1021,
                ], [
                    'slug' => 'arranger',
                    'name' => 'Arranger',
                    'title' => 'Arranged by',
                    'collection' => 'Arrangements',
                    'sequence' => 1020,
                ]
            ])
            ->save();
    }
}
