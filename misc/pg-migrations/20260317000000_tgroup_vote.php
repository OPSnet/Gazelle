<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class TgroupVote extends AbstractMigration {
    public function up(): void {
        $this->query('
            create or replace function binomial_ci(p int, n int)
                returns float
                immutable
                language plpgsql
            as $$
                declare
                    result float;
                begin
                    if n = 0 then
                        result := 0.0;
                    else
                        result := ((p + 1.35336) / n - 1.6452 * sqrt((p * (n - p))::float / n + 0.67668) / n) / (1 + 2.7067 / n);
                    end if;
                    return result;
                end;
            $$;
        ');

        $this->table('tgroup_vote', ['id' => false, 'primary_key' => ['id_tgroup', 'id_user']])
            ->addColumn('id_tgroup', 'integer')
            ->addColumn('id_user', 'integer')
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('upvote', 'boolean')
            ->addIndex('id_user', ['name' => 'tv_u_idx'])
            ->save();

        $this->query('
            create table tgroup_vote_summary (
                id_tgroup integer not null primary key,
                total integer not null,
                upvote integer not null,
                score float generated always as (binomial_ci(upvote, total)) stored
            )
        ');
    }

    public function down(): void {
        $this->table('tgroup_vote_summary')->drop()->save();
        $this->table('tgroup_vote')->drop()->save();
        $this->query('
            drop function binomial_ci(p int, n int)
        ');
    }
}
