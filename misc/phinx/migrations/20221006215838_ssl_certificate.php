<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SslCertificate extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
            ->insert([
                [
                    'name' => 'SSL Certificates',
                    'classname' => 'SSLCertificate',
                    'description' => 'Check the expiry of SSL certificates',
                    'period' => 86400 * 2,
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->execute("
            DELETE FROM periodic_task WHERE classname = 'SSLCertificate'
        ");
    }
}
