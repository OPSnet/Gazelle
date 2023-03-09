<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ArtistArranger extends AbstractMigration
{
    public function up(): void
    {
        $this->table('requests_artists')
            ->changeColumn('Importance', 'enum', [
                'null' => false, 'values' => ['1', '2', '3', '4', '5', '6', '7', '8'],
            ])
            ->save();
        $this->table('torrents_artists')
            ->changeColumn('Importance', 'enum', [
                'null' => false, 'values' => ['1', '2', '3', '4', '5', '6', '7', '8'],
            ])
            ->save();
    }

    public function down(): void
    {
        $this->table('requests_artists')
            ->changeColumn('Importance', 'enum', [
                'null' => true, 'values' => ['1', '2', '3', '4', '5', '6', '7'],
            ])
            ->save();
        $this->table('torrents_artists')
            ->changeColumn('Importance', 'enum', [
                'null' => true, 'values' => ['1', '2', '3', '4', '5', '6', '7'],
            ])
            ->save();
    }
}
