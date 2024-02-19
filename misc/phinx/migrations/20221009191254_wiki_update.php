<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class WikiUpdate extends AbstractMigration {
    public function up(): void {
        $this->execute("
            ALTER TABLE wiki_articles MODIFY `Date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE wiki_articles MODIFY `Date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }
}
