<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ErrorLogVars extends AbstractMigration
{
    public function up(): void {
        $this->table('error_log')
            ->addColumn('logged_var', 'json', ['null' => true])
            ->save();
        $this->query("UPDATE error_log SET logged_var = '[]'");
        $this->table('error_log')
            ->changeColumn('logged_var', 'json', ['null' => false])
            ->save();
    }

    public function down(): void {
        $this->table('error_log')
            ->removeColumn('logged_var')
            ->save();
    }
}
