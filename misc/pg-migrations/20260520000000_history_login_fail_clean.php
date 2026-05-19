<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class HistoryLoginFailClean extends AbstractMigration {
    public function up(): void {
        $this->query("
            delete from ip_history where data_origin = 'login-fail'
        ");
    }

    public function down(): void {
        // bad luck, dig them out of history_login_fail if you must
    }
}
