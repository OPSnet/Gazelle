<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NonMusicRequestReleaseType extends AbstractMigration
{
    public function up(): void
    {
        $this->query("ALTER TABLE requests MODIFY ReleaseType tinyint(2) NOT NULL DEFAULT 21");
    }

    public function down(): void
    {
        $this->query("ALTER TABLE requests MODIFY ReleaseType tinyint(2) DEFAULT NULL");
    }
}
