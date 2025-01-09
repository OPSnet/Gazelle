<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NotificationTicket extends AbstractMigration {
    public function up(): void {
        $this->execute('CREATE OR REPLACE FUNCTION modified_now() RETURNS TRIGGER AS $$
            BEGIN
                NEW.modified = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql');

        $this->execute("CREATE TYPE nt_state AS ENUM ('pending', 'stale', 'active', 'done', 'removed', 'error')");
        $this->execute('CREATE TABLE notification_ticket (
            id_torrent INTEGER NOT NULL PRIMARY KEY,
            state nt_state DEFAULT \'pending\',
            reach INTEGER DEFAULT 0,
            retry INTEGER DEFAULT 0,
            created TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modified TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->execute('CREATE INDEX nt_state_idx ON notification_ticket (state)');
        $this->execute('CREATE TRIGGER notification_ticket_modified
            BEFORE UPDATE ON notification_ticket
            FOR EACH ROW
            EXECUTE FUNCTION modified_now()');
    }

    public function down(): void {
        $this->table('notification_ticket')->drop()->save();
        $this->execute('DROP TYPE nt_state');
        $this->execute('DROP FUNCTION modified_now');
    }
}
