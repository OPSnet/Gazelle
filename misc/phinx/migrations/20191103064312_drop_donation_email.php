<?php

use Phinx\Migration\AbstractMigration;

class DropDonationEmail extends AbstractMigration
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
    public function up(): void {
        /*
        $this->execute("
            ALTER TABLE users_donor_ranks DROP COLUMN Email
        ");
        */
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE users_donor_ranks ADD COLUMN Email varchar(255)
        ");
    }
}
