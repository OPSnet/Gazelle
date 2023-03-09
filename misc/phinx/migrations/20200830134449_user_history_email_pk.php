<?php

use Phinx\Migration\AbstractMigration;

class UserHistoryEmailPk extends AbstractMigration {
    public function up(): void {
        $this->execute('
            ALTER TABLE users_history_emails ADD COLUMN users_history_emails_id int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY
        ');
    }
    public function down(): void {
        $this->execute('
            ALTER TABLE users_history_emails DROP COLUMN users_history_emails_id
        ');
    }
}
