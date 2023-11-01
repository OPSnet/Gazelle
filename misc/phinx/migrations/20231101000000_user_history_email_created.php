<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserHistoryEmailCreated extends AbstractMigration {
    public function up(): void {
        $this->table('users_history_emails')
            ->changeColumn('Email', 'string', ['length' => 255, 'null' => false])
            ->changeColumn('IP',    'string', ['length' =>  15, 'null' => false])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->save();
        // if we have a value in the Time column, we can use that, and fall back
        // to the date the account was created.
        $this->execute("
            UPDATE users_history_emails SET created = Time where Time IS NOT NULL
        ");
        $this->execute("
            UPDATE users_history_emails uhe
            INNER JOIN users_main um ON (um.ID = uhe.UserID)
            SET uhe.created = um.created where uhe.Time IS NULL
        ");
    }

    public function down(): void {
        $this->table('users_history_emails')
            ->changeColumn('Email', 'string', ['length' => 255, 'null' => true])
            ->changeColumn('IP',    'string', ['length' =>  15, 'null' => true])
            ->removeColumn('created')
            ->save();
    }
}
