<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class UnicodeInbox extends AbstractMigration
{
    public function up(): void {
        $this->table('pm_conversations')
            ->changeColumn('Subject', 'string', ['null' => false, 'limit' => 255, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->save();
        $this->table('pm_messages')
            ->changeColumn('Body', 'text', ['null' => false, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->save();
        $this->table('staff_pm_conversations')
            ->changeColumn('Subject', 'string', ['null' => false, 'limit' => 255, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->changeColumn('UserID', 'integer', ['null' => false, 'limit' => 11])
            ->changeColumn('Status', 'enum', [ 'null' => false, 'default' => 'Unanswered', 'values' => ['Open', 'Unanswered', 'Resolved']])
            ->changeColumn('Level', 'integer', [ 'null' => false, 'default' => 0])
            ->changeColumn('Date', 'datetime', [ 'null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->changeColumn('Unread', 'boolean', [ 'null' => false, 'default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->save();
        $this->table('staff_pm_messages')
            ->changeColumn('UserID', 'integer', ['null' => false, 'limit' => 11])
            ->changeColumn('SentDate', 'datetime', [ 'null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->changeColumn('Message', 'text', ['null' => false, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->changeColumn('ConvID', 'integer', ['null' => false, 'limit' => 11])
            ->save();
        $this->table('staff_pm_responses')
            ->changeColumn('Message', 'text', ['null' => false, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->changeColumn('Name', 'string', ['null' => false, 'limit' => 255, 'collation' => 'utf8mb4_unicode_ci', 'encoding' => 'utf8mb4'])
            ->save();
    }

    public function down(): void {
        $this->table('pm_conversations')
            ->changeColumn('Subject', 'string', ['null' => true, 'limit' => 255, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->save();
        $this->table('pm_messages')
            ->changeColumn('Body', 'text', ['null' => true, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->save();
        $this->table('staff_pm_conversations')
            ->changeColumn('Subject', 'string', ['null' => true, 'limit' => 255, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->changeColumn('UserID', 'integer', ['null' => true, 'default' => null, 'limit' => 11])
            ->changeColumn('Status', 'enum', [ 'null' => true, 'values' => ['Open', 'Unanswered', 'Resolved']])
            ->changeColumn('Level', 'integer', [ 'null' => true, 'default' => null])
            ->changeColumn('Date', 'datetime', [ 'null' => true, 'default' => null])
            ->changeColumn('Unread', 'boolean', [ 'null' => true, 'default' => null, 'limit' => MysqlAdapter::INT_TINY])
            ->save();
        $this->table('staff_pm_messages')
            ->changeColumn('UserID', 'integer', ['null' => true, 'default' => null, 'limit' => 11])
            ->changeColumn('SentDate', 'datetime', [ 'null' => true, 'default' => null])
            ->changeColumn('Message', 'text', ['null' => true, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->changeColumn('ConvID', 'integer', ['null' => true, 'default' => null, 'limit' => 11])
            ->save();
        $this->table('staff_pm_responses')
            ->changeColumn('Message', 'text', ['null' => true, 'default' => null, 'limit' => MysqlAdapter::TEXT_MEDIUM, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->changeColumn('Name', 'string', ['null' => true, 'limit' => 255, 'collation' => 'utf8_general_ci', 'encoding' => 'utf8'])
            ->save();
    }
}
