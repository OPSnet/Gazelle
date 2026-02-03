<?php

use Phinx\Migration\AbstractMigration;

class StylesheetOrpheusHaze extends AbstractMigration {
    public function up(): void {
        $this->table('stylesheets')->insert([
            ['Name' => 'Orpheus Haze', 'Description' => 'Orpheus Haze by burtoo + rubric_alert', 'theme' => 'dark'],
        ])->save();

        $this->execute("
            UPDATE users_main
            SET stylesheet_id = (
                SELECT ID FROM stylesheets WHERE Name = 'Orpheus Haze' LIMIT 1
            )
            WHERE stylesheet_id = (
                SELECT ID FROM stylesheets WHERE Name = 'LinoHaze' LIMIT 1
            )
        ");

        $this->execute("
            DELETE FROM stylesheets WHERE Name = 'LinoHaze'
        ");
    }

    public function down(): void {
        $this->execute("
            INSERT INTO stylesheets (Name, Description, theme)
            SELECT 'LinoHaze', 'LinoHaze by linotype', 'dark'
            WHERE NOT EXISTS (
                SELECT 1 FROM stylesheets WHERE Name = 'LinoHaze'
            )
        ");

        $this->execute("
            UPDATE users_main
            SET stylesheet_id = (
                SELECT ID FROM stylesheets WHERE Name = 'LinoHaze' LIMIT 1
            )
            WHERE stylesheet_id = (
                SELECT ID FROM stylesheets WHERE Name = 'Orpheus Haze' LIMIT 1
            )
        ");

        $this->execute("
            DELETE FROM stylesheets WHERE Name = 'Orpheus Haze'
        ");
    }
}
