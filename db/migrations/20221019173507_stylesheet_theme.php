<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StylesheetTheme extends AbstractMigration {
    public function up(): void {
        $this->table('stylesheets')
             ->addColumn('theme', 'enum', ['default' => 'dark', 'values' => ['dark', 'light']])
             ->save();
        $this->execute("update stylesheets set theme = 'light' where name in ('Layer cake', 'Proton', 'Xanax cake', 'Post Office')");
    }

    public function down(): void {
        $this->table('stylesheets')
             ->removeColumn('theme')
             ->save();
    }
}
