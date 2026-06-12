<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class LoginWatch extends AbstractMigration {
    public function up(): void {
        // Phinx/PDO are so brain-damaged that they refuse to allow primary key on inet
        $this->query('
            create table login_watch (
                ip            inet not null primary key,
                modified      timestamptz not null default current_timestamp,
                blocked_until timestamptz,
                block         integer not null default 0,
                attempt       integer not null default 0,
                id_user       integer,
                capture       varchar(80)
            )
        ');
        $this->query('
            create trigger login_watch_modified
            before update on login_watch
            for each row
            execute function modified_now()
        ');
    }

    public function down(): void {
        $this->table('login_watch')->drop()->save();
    }
}
