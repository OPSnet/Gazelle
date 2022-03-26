<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * On a small site, or if the site is offline it is safe to run this
 * migration directly. If you are sure, then set the environment variable
 * LOCK_MY_DATABASE to a value that evaluates as truth, e.g. 1 and then run
 * again.
 */
class DropSecret extends AbstractMigration
{
    public function up()
    {
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
        $this->table('users_main')
             // ->removeColumn('Secret')
             ->changeColumn('Title', 'string', ['limit' => 255, 'default' => ''])
             ->update();

        $this->table('users_info')
             ->changeColumn('Info', 'text', ['limit' => 65536])
             ->changeColumn('AdminComment', 'text', ['limit' => 65536])
             ->changeColumn('SiteOptions', 'text', ['limit' => 65536])
             ->changeColumn('Warned', 'timestamp', ['null' => true])
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
        if (!getenv('LOCK_MY_DATABASE')) {
            die("Migration cannot proceed, use the source: " . __FILE__ . "\n");
        }
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
            ->update();

        $this->table('torrents_group')
             ->changeColumn('TagList', 'string', [
                'null' => false,
                'limit' => 500,
                'collation' => 'utf8_general_ci',
                'encoding' => 'utf8',
             ])
             ->update();
    }
}
