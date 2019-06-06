<?php


use Phinx\Migration\AbstractMigration;

class RequestChecksum extends AbstractMigration
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
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $this->table('requests')->addColumn('Checksum', 'boolean', ['default' => false])
            ->update();
    }
    public function up()
    {
        $this->execute('ALTER TABLE requests MODIFY TimeFilled datetime');
        $this->execute("UPDATE requests SET timefilled = null WHERE timefilled = '0000-00-00 00:00:00'");
    }
    public function down()
    {
        $this->execute("ALTER TABLE requests MODIFY TimeFilled datetime NOT NULL DEFAULT '0000-00-00 00:00:00'");
        $this->execute("UPDATE requests SET TimeFilled = '0000-00-00 00:00:00' WHERE TimeFilled IS NULL");
    }
}
