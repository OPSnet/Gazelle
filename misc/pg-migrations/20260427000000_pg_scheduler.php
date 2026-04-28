<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class PgScheduler extends AbstractMigration {
    public function up(): void {
        $this->table('task', ['id' => false, 'primary_key' => 'id_task'])
            ->addColumn('id_task', 'integer', ['identity' => true])
            ->addColumn('period',      'integer')
            ->addColumn('is_enabled',  'boolean', ['default' => true])
            ->addColumn('is_sane',     'boolean', ['default' => true])
            ->addColumn('is_debug',    'boolean', ['default' => false])
            ->addColumn('run_now',     'boolean', ['default' => false])
            ->addColumn('name',        'string', ['length' => 64])
            ->addColumn('class_name',  'string', ['length' => 32])
            ->addColumn('description', 'string', ['length' => 100])
            ->addIndex(['name'], ['unique' => true, 'name' => 'pt_n_uidx'])
            ->addIndex(['class_name'], ['unique' => true, 'name' => 'pt_cn_uidx'])
            ->save();

        $this->query("
            create type task_status_t as enum ('running', 'completed', 'failed')
        ");

        $this->table('task_history', ['id' => false, 'primary_key' => 'id_task_history'])
            ->addColumn('id_task_history', 'integer', ['identity' => true])
            ->addColumn('id_task', 'integer')
            ->addColumn('error_total', 'integer', ['default' => 0])
            ->addColumn('item_total',  'integer', ['default' => 0])
            ->addColumn('duration_ms', 'integer', ['default' => 0])
            ->addColumn('created',     'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('status',      Literal::from('task_status_t'), ['default' => 'running'])
            ->addIndex(['created'], ['name' => 'pth_c_idx'])
            ->addIndex(['id_task'], ['name' => 'th_t_idx'])
            ->addForeignKey('id_task', 'task', 'id_task', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->query("
            create type task_event_severity_t as enum ('error', 'info', 'debug')
        ");

        $this->table('task_history_event', ['id' => false, 'primary_key' => 'id_task_history_event'])
            ->addColumn('id_task_history_event', 'integer', ['identity' => true])
            ->addColumn('id_task_history',       'integer')
            ->addColumn('reference', 'integer')
            ->addColumn('created',   'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('severity',  Literal::from('task_event_severity_t'))
            ->addColumn('note',      'string', ['length' => 1000])
            ->addIndex(['id_task_history'], ['name' => 'the_th_idx'])
            ->addForeignKey('id_task_history', 'task_history', 'id_task_history', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->query('
            insert into task (id_task, period, is_enabled, is_sane, is_debug, run_now, name, class_name, description)
            select periodic_task_id,
                period,
                case when is_enabled = 0 then false else true end,
                case when is_sane    = 0 then false else true end,
                case when is_debug   = 0 then false else true end,
                case when run_now    = 0 then false else true end,
                name,
                classname,
                description
            from relay.periodic_task
        ');
        $this->query(<<<END_SQL
            insert into task_history (id_task, error_total, item_total, duration_ms, created, status)
            select
                periodic_task_id,
                num_errors,
                num_items,
                duration_ms,
                launch_time,
                case when status = 'running'::periodic_task_history_status_t then 'running'::task_status_t
                     when status = 'failed'::periodic_task_history_status_t then 'failed'::task_status_t
                     else 'completed'::task_status_t
                end
            from relay.periodic_task_history
            END_SQL
        );
        /* not going to bother about task_history_event, too much effort for to little gain */

        $this->query("
            select
                setval(
                    'task_id_task_seq',
                    (select max(id_task) from task)
                ),
                setval(
                    'task_history_id_task_history_seq',
                    (select max(id_task_history) from task_history)
                )
        ");
    }

    public function down(): void {
        $this->table('task_history_event')->drop()->save();
        $this->table('task_history')->drop()->save();
        $this->table('task')->drop()->save();

        $this->query("
            drop type task_event_severity_t
        ");
        $this->query("
            drop type task_status_t
        ");
    }
}
