<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropRequestTag extends AbstractMigration {
    public function up(): void {
        $this->table('request_tag')->drop()->save();
        $this->query('create index r_t_idx on request using gin (tag)');
    }

    public function down(): void {
        $this->query("
            create table request_tag (
                id_request int not null,
                id_tag     int not null,
                primary key (id_request, id_tag)
            )
        ");
        $this->query("
            create index reqt_t_idx on request_tag (id_tag)
        ");
        $this->table('request_tag')
            ->addForeignKey('id_request', 'request', 'id_request', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
        $this->query('drop index r_t_idx');
    }
}
