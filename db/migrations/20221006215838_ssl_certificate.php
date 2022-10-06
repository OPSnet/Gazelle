<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SslCertificate extends AbstractMigration {
    public function up() {
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

    public function down()
    {
        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(function ($exp) {
                return $exp->in('classname', ['SSLCertificate']);
            })
            ->execute();
    }
}
