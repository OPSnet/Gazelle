<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ArtistNameNotNull extends AbstractMigration
{
    public function up(): void {
        $this->table('artists_group')
            ->changeColumn('Name', 'string', ['length' => 200, 'null' => false])
            ->save();
    }

    public function down(): void {
        $this->table('artists_group')
            ->changeColumn('Name', 'string', ['length' => 200, 'null' => true])
            ->save();
    }
}
