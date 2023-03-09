<?php

use Phinx\Migration\AbstractMigration;

class EmailHistoryDate extends AbstractMigration {
    public function up(): void {
        $this->execute("
            UPDATE users_history_emails uhm
            INNER JOIN users_info ui USING (UserID)
            SET
                uhm.Time = ui.JoinDate
            WHERE uhm.Time IS NULL
        ");
        $this->execute("ALTER TABLE users_history_emails MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down(): void {
        $this->execute("ALTER TABLE users_history_emails MODIFY Time datetime DEFAULT NULL");
    }
}
