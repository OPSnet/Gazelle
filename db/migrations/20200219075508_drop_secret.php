<?php

use Phinx\Migration\AbstractMigration;

class DropSecret extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up()
    {
        $this->table('users_main')
             ->removeColumn('Secret')
             ->changeColumn('Title', 'string', ['limit' => 255, 'default' => ''])
             ->update();

        $this->table('users_info')
             ->changeColumn('Info', 'text', ['limit' => 65536, 'default' => ''])
             ->changeColumn('AdminComment', 'text', ['limit' => 65536, 'default' => ''])
             ->changeColumn('SiteOptions', 'text', ['limit' => 65536, 'default' => ''])
             ->changeColumn('Warned', 'timestamp', ['default' => '0000-00-00 00:00:00'])
             ->changeColumn('Avatar', 'string', ['limit' => 255, 'default' => ''])
             ->changeColumn('SupportFor', 'string', ['limit' => 255, 'default' => ''])
             // who needs self documenting enum values when you have comments?
             ->changeColumn('TorrentGrouping', 'enum', ['values' => ['0', '1', '2'], 'default' => '0', 'comment' => '0=Open,1=Closed,2=Off'])
             ->changeColumn('ResetKey', 'string', ['limit' => 32, 'default' => ''])
             ->changeColumn('InfoTitle', 'string', ['limit' => 255, 'default' => ''])
             ->changeColumn('NavItems', 'string', ['limit' => 255, 'default' => ''])
             ->update();

        $this->table('forums_topics')
             ->changeColumn('LastPostID', 'integer', ['limit' => 10, 'default' => 0])
             ->update();

        $this->table('artists_group')
             ->changeColumn('VanityHouse', 'boolean', ['default' => false])
             ->update();

        $this->table('torrents_group')
             ->changeColumn('TagList', 'string', ['limit' => 500, 'default' => ''])
             ->update();
    }

    public function down()
    {
        $this->table('users_main')
            ->addColumn('Secret', 'char', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('Title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8'
            ])
            ->update();

        $this->table('users_info')
            ->changeColumn('Info', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8'
            ])
            ->changeColumn('Avatar', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('AdminComment', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('SiteOptions', 'text', [
                'null' => false,
                'limit' => 65535,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('Warned', 'datetime', [
                'null' => false,
            ])
            ->changeColumn('SupportFor', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('TorrentGrouping', 'enum', [
                'null' => false,
                'limit' => 1,
                'values' => ['0', '1', '2'],
                'comment' => '0=Open,1=Closed,2=Off',
            ])
            ->changeColumn('ResetKey', 'string', [
                'null' => false,
                'limit' => 32,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('InfoTitle', 'string', [
                'null' => false,
                'limit' => 255,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
            ])
            ->changeColumn('NavItems', 'string', ['limit' => 200])
            ->update();

        $this->table('forums_topics')
            ->changeColumn('LastPostID', 'integer', [
                'null' => false,
                'limit' => '10',
            ])
            ->update();

        $this->table('artists_group')
            ->changeColumn('VanityHouse', 'boolean', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->change();

        $this->table('torrents_group')
             ->changeColumn('TagList', 'string', [
                'null' => false,
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
             ])
             ->change();
    }
}
