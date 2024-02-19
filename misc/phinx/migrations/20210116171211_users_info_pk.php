<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// This migration is dealing with an issue in production with the schema
// having a unique key constraint, but was lacking a primary key. This
// does not exist though within the schema created from this repo, so we
// have to work around that.
final class UsersInfoPk extends AbstractMigration
{
    public function up(): void {
        $alter = [];
        $table = $this->table('users_info');
        if ($table->hasIndex('UserID')) {
            $alter[] = 'DROP KEY UserID';
        }
        if (!$table->getAdapter()->hasPrimaryKey($table->getName(), 'UserID')) {
            $alter[] = 'ADD PRIMARY KEY (UserID)';
        }

        if (count($alter) > 0) {
            $this->execute("ALTER TABLE users_info " . implode(', ', $alter));
        }
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE users_info
                ADD UNIQUE KEY UserID (UserID),
                DROP PRIMARY KEY
        ");
    }
}
