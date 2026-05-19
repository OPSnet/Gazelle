<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class HistoryLoginFail extends AbstractMigration {
    public function up(): void {
        $this->table('history_login_fail', ['id' => false, 'primary_key' => 'id_history_login_fail'])
            ->addColumn('id_history_login_fail', 'integer', ['identity' => true])
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('ip', Literal::from('inet'))
            ->addColumn('username', 'string', ['length' => 20])
            ->save();
        $this->query("
            create index on history_login_fail (created)
        ");
        $this->query("
            create index on history_login_fail using gist (ip inet_ops)
        ");
        $this->query("
            insert into task
                   (name, class_name, description, period)
            values (
                'Purge history_login_fail',
                'LoginFailPurge',
                'Purge old rows from history_login_fail (older than one year)',
                86400 * 7
            )
        ");
        $this->query(<<<END_SQL
            insert into history_login_fail
                (ip, created, username)
            select ip,
                upper(seen),
                um."Username"
            from ip_history h
            inner join relay.users_main um on (um."ID" = h.id_user)
            where data_origin = 'login-fail'
            END_SQL
        );
    }

    public function down(): void {
        $this->query("
            delete from task where class_name = 'LoginFailPurge'
        ");
        $this->table('history_login_fail')->drop()->save();
    }
}
