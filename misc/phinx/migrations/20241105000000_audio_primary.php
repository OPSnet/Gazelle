<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AudioPrimary extends AbstractMigration {
    public function up(): void {
        $this->query("
            update user_ordinal set name = 'non-primary-threshold' where name = 'non-audio-threshold';
        ");
    }

    public function down(): void {
        $this->query("
            update user_ordinal set name = 'non-audio-threshold' where name = 'non-primary-threshold';
        ");
    }
}
