<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UsersInfoPk extends AbstractMigration
{
    public function up(): void {
        $this->execute("
            ALTER TABLE users_info
                DROP KEY UserID,
                ADD PRIMARY KEY (UserID)
        ");
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE users_info
                ADD UNIQUE KEY UserID (UserID),
                DROP PRIMARY KEY
        ");
    }
}
