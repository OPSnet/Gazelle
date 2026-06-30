<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class NotificationTicketStateNonnull extends AbstractMigration {
    public function up(): void {
        $this->table('notification_ticket')
            ->changeColumn('state', Literal::from('nt_state'), ['null' => false, 'default' => 'pending'])
            ->save();
    }

    public function down(): void {
        $this->table('notification_ticket')
            ->changeColumn('state', Literal::from('nt_state'), ['null' => true, 'default' => 'pending'])
            ->save();
    }
}
