<?php

use Phinx\Migration\AbstractMigration;

class StylesheetOrpheusPaper extends AbstractMigration {
    public function up(): void {
        $this->table('stylesheets')->insert([
            ['Name' => 'Orpheus Paper', 'Description' => 'Orpheus Paper by burtoo + rubric_alert'],
        ])->save();
    }

    public function down(): void {
        $this->execute("
            DELETE FROM stylesheets WHERE Name = 'Orpheus Paper'
        ");
    }
}
