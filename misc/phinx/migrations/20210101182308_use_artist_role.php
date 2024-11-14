<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class UseArtistRole extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void {
        $this->table('torrents_artists')
            ->addColumn('artist_role_id', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_TINY])
            ->addForeignKey('artist_role_id', 'artist_role', 'artist_role_id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
            ->update();
        $this->table('requests_artists')
            ->addColumn('artist_role_id', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_TINY])
            ->addForeignKey('artist_role_id', 'artist_role', 'artist_role_id',
                ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
            ->update();

        $this->execute('UPDATE torrents_artists SET artist_role_id = Importance');
        $this->execute('UPDATE requests_artists SET artist_role_id = Importance');
    }
}
