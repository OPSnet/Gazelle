<?php

use Phinx\Migration\AbstractMigration;

class UserFreeleechTable extends AbstractMigration {
    public function up(): void {
        $this->table('user_flt', ['id' => false, 'primary_key' => 'user_id'])
            ->addColumn('user_id', 'integer', ['limit' => 10, 'signed' => false])
            ->addColumn('tokens', 'integer', ['default' => 0])
            ->addForeignKey('user_id', 'users_main', 'ID')
            ->save();
        $this->execute("
            INSERT INTO user_flt
            SELECT ID, FLTokens
            FROM users_main
        ");
    }

    public function down(): void {
        $this->table('user_flt')->drop()->update();
    }
}
