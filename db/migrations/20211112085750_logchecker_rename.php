<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LogcheckerRename extends AbstractMigration
{
    public function up(): void
    {
        $this->getQueryBuilder()
            ->update('nav_items')
            ->set('title', 'Logchecker')
            ->where(['tag' => 'logchecker'])
            ->execute();
    }

    public function down(): void
    {
        $this->getQueryBuilder()
            ->update('nav_items')
            ->set('title', 'Log Checker')
            ->where(['tag' => 'logchecker'])
            ->execute();
    }
}
