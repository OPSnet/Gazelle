<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StylesheetOrpheusPaperTheme extends AbstractMigration {
    public function up(): void {
        $this->execute("
            UPDATE stylesheets
            SET theme = 'light'
            WHERE Name = 'Orpheus Paper'
        ");
    }

    public function down(): void {
        $this->execute("
            UPDATE stylesheets
            SET theme = DEFAULT
            WHERE Name = 'Orpheus Paper'
        ");
    }
}
