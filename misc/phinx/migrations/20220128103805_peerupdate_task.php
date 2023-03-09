<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PeerupdateTask extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
             ->insert([
                 [
                     'name' => 'Peerupdate',
                     'classname' => 'Peerupdate',
                     'description' => 'Update the cached peer counts of torrent groups',
                     'period' => 60 * 15,
                 ],
             ])
             ->save();
    }

    public function down(): void {
        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(function ($exp) {
                return $exp->in('classname', ['Peerupdate']);
            })
            ->execute();
    }
}
