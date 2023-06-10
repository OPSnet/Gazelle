<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LargerArtistSummary extends AbstractMigration {
    public function up(): void {
        $this->table('wiki_artists')
            ->changeColumn('summary', 'string', ['length' => 400, 'default' => ''])
            ->save();
    }

    public function down(): void {
        $this->table('wiki_artists')
            ->changeColumn('summary', 'string', ['length' => 100, 'null' => true])
            ->save();
    }
}
