<?php

use Phinx\Migration\AbstractMigration;

class ForumTransitionPermissions extends AbstractMigration
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
        $this->table('forums_transitions')
             ->addColumn('permission_class', 'integer', ['limit' => 10, 'signed' => false, 'default' => 800])
             ->addColumn('permissions', 'string', ['limit' => 100, 'default' => ''])
             ->addColumn('user_ids', 'string', ['limit' => 100, 'default' => ''])
             ->update();

        $this->table('forums_topic_notes')
             ->changeColumn('AddedTime', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
             ->update();
    }

    public function down()
    {
        $this->table('forums_transitions')
             ->removeColumn('permission_class')
             ->removeColumn('permissions')
             ->removeColumn('user_ids')
             ->update();

        $this->table('forums_topic_notes')
             ->changeColumn('AddedTime', 'datetime')
             ->update();
    }
}
