<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IpHistoryCleanup extends AbstractMigration {
    public function up(): void {
        $this->execute("
            delete from ip_history ih using ip_site_history
            where ih.ip = ip_site_history.ip
                and ih.id_user = ip_site_history.id_user
                and data_origin = 'site'
        ");
        $this->execute("
            update ip_history set data_origin='token' where data_origin='site'
        ");
    }

    public function down(): void {
        $this->execute("
            update ip_history set data_origin='site' where data_origin='token'
        ");
    }
}
