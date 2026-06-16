<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MoreGifts extends AbstractMigration {
    public function up(): void {
        $this->query("
            update gift set factor = factor * 1024 where label ~ '^(down|up)-'
        ");
        $this->table('gift', ['id' => false, 'primary_key' => 'id_gift'])
             ->insert([
                [
                    'description' => 'Add 500 MiB to your upload stats',
                    'duration'    => 0,
                    'label'       => 'up-05',
                    'factor'      => 512,
                    'weight'      => 150000,
                ],
                [
                    'description' => 'Add 250 MiB to your upload stats',
                    'duration'    => 0,
                    'label'       => 'up-02',
                    'factor'      => 256,
                    'weight'      => 250000,
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->query("
            delete from gift where label ~ '^up-0[52]$'
        ");
        $this->query("
            update gift set factor = factor / 1024 where label ~ '^(down|up)-'
        ");
    }
}
