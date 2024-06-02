<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IpSiteHistory extends AbstractMigration {
    public function up(): void {
        $this->execute("
            create table ip_site_history(
                id_user int not null,
                ip inet not null,
                total int not null default 1,
                seen tstzmultirange not null default tstzmultirange(tstzrange(now(), now(), '[]')),
                primary key (id_user, ip)
            )
        ");
        $this->execute("
            create index on ip_site_history using gist (ip inet_ops)
        ");
        $this->execute("
            create index on ip_site_history using gist (seen)
        ");
    }

    public function down(): void {
        $this->execute("
            drop table ip_site_history
        ");
    }
}
