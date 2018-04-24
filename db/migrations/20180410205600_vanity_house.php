<?php

use Phinx\Migration\AbstractMigration;

class VanityHouse extends AbstractMigration
{
    public function change()
    {
        $this->table('featured_albums')
            ->addColumn('Type', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->update();
    }
}