<?php

use Phinx\Migration\AbstractMigration;

class DropUserMainColumns extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE users_main
                DROP COLUMN LastLogin,
                DROP COLUMN LastAccess,
                DROP COLUMN BonusPoints,
                DROP COLUMN RequiredRatioWork,
                DROP COLUMN FLTokens,
                DROP COLUMN FLT_Given,
                DROP COLUMN Invites_Given
        ');
    }
    public function down(): void {
        $this->execute("
            ALTER TABLE users_main
                ADD COLUMN LastLogin datetime NOT NULL DEFAULT NULL,
                ADD COLUMN LastAccess datetime NOT NULL DEFAULT NULL,
                ADD COLUMN BonusPoints float(20,5) NOT NULL DEFAULT 0.00000,
                ADD COLUMN RequiredRatioWork double(12,8) NOT NULL DEFAULT 0.00000000,
                ADD COLUMN FLTokens int(10) NOT NULL DEFAULT 0,
                ADD COLUMN FLT_Given int(10) NOT NULL DEFAULT 0,
                ADD COLUMN Invites_Given int(10) NOT NULL DEFAULT 0
        ");
    }
}
