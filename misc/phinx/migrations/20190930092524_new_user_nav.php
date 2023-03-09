<?php


use Phinx\Migration\AbstractMigration;

class NewUserNav extends AbstractMigration {
    public function up(): void {
        $this->table('nav_items')
             ->addColumn('initial', 'boolean', ['default' => false])
             ->renameColumn('ID', 'id')
             ->renameColumn('Key', 'tag')
             ->renameColumn('Title', 'title')
             ->renameColumn('Target', 'target')
             ->renameColumn('Tests', 'tests')
             ->renameColumn('TestUser', 'test_user')
             ->renameColumn('Mandatory', 'mandatory')
             ->update();

        $this->execute("
            UPDATE nav_items
            SET initial = 1
            WHERE id IN (1,2,3,4,5,6,7,8,9,10)");

        $this->execute("
            UPDATE users_info
            SET NavItems = '1,2,3,4,5,6,7,8,9,10'
            WHERE NavItems IN ('', '1,2')");
    }

    public function down(): void {
        $this->table('nav_items')
             ->removeColumn('initial')
             ->renameColumn('id', 'ID')
             ->renameColumn('tag', 'Key')
             ->renameColumn('title', 'Title')
             ->renameColumn('target', 'Target')
             ->renameColumn('tests', 'Tests')
             ->renameColumn('test_user', 'Test_Tser')
             ->renameColumn('mandatory', 'Mandatory')
             ->update();
    }
}
