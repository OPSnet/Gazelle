<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// This column is already covered by the PK

final class TorrentsArtistsDropGroupIdx extends AbstractMigration
{
    public function up(): void {
        $this->table('torrents_artists')
            ->removeIndex(['GroupID'])
            ->save();
    }

    public function down(): void {
        $this->table('torrents_artists')
            ->addIndex(['GroupID'])
            ->save();
    }
}
