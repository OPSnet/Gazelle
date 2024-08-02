<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PeerupdateTask extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
            ->insert([
                [
                    'name' => 'Peerupdate',
                    'classname' => 'Peerupdate',
                    'description' => 'Update the cached peer counts of torrent groups',
                    'period' => 60 * 15,
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->execute("
            DELETE FROM periodic_task WHERE classname = 'Peerupdate'
        ");
    }
}
