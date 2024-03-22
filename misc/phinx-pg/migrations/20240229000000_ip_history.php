<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IpHistory extends AbstractMigration {
    public function up(): void {
        $this->execute("
            create type data_origin_t as enum (
                'email',
                'interview',
                'login-fail',
                'password',
                'registration',
                'site',
                'token',
                'tracker'
            );
        ");
        $this->execute("
            create table ip_history(
                id_user int not null,
                ip inet not null,
                data_origin data_origin_t not null,
                total int not null default 1,
                seen tstzrange not null default tstzrange(now(), now(), '[]'),
                primary key (id_user, ip, data_origin)
            )
        ");
        $this->execute("
            create index on ip_history using gist (ip inet_ops)
        ");
        $this->execute("
            create index on ip_history using gist (seen)
        ");
    }

    public function down(): void {
        $this->execute("
            drop table ip_history
        ");
        $this->execute("
            drop type data_origin_t
        ");
    }
}
