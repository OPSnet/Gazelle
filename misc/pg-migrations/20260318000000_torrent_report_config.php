<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class TorrentReportConfig extends AbstractMigration {
    public function up(): void {
        $this->table('torrent_report_configuration', ['id' => false, 'primary_key' => ['id_torrent_report_configuration']])
            ->addColumn('id_torrent_report_configuration', 'integer', ['identity' => true])
            ->addColumn('id_category',    'integer')
            ->addColumn('sequence',       'integer')
            ->addColumn('tracker_reason', 'integer', ['default' => -1])
            ->addColumn('resolve_warn',   'integer', ['default' => 0])
            ->addColumn('need_image',     Literal::from('torrent_report_configuration_need_image_t'),    ['default' => 'none'])
            ->addColumn('need_link',      Literal::from('torrent_report_configuration_need_link_t'),     ['default' => 'none'])
            ->addColumn('need_sitelink',  Literal::from('torrent_report_configuration_need_sitelink_t'), ['default' => 'none'])
            ->addColumn('need_track',     Literal::from('torrent_report_configuration_need_track_t'),    ['default' => 'none'])
            ->addColumn('is_active',      'boolean', ['default' => true])
            ->addColumn('is_admin',       'boolean', ['default' => false])
            ->addColumn('is_invisible',   'boolean', ['default' => false])
            ->addColumn('resolve_delete', 'boolean', ['default' => false])
            ->addColumn('resolve_upload', 'boolean', ['default' => false])
            ->addColumn('type',           'string', ['length' => 20])
            ->addColumn('name',           'string', ['length' => 30])
            ->addColumn('resolve_log',    'string', ['length' => 80, 'null' => true])
            ->addColumn('explanation',    'text')
            ->addColumn('pm_body',        'text', ['null' => true])
            ->addForeignKey('id_category', 'category', 'id_category')
            ->addIndex(['type'], ['unique' => true, 'name' => 'trc_type_uidx'])
            ->create();

        $this->table('torrent_report_configuration_log', ['id' => false, 'primary_key' => ['id_torrent_report_configuration_log']])
            ->addColumn('id_torrent_report_configuration_log', 'integer', ['identity' => true])
            ->addColumn('id_torrent_report_configuration', 'integer')
            ->addColumn('id_user', 'integer')
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('change_set', 'json')
            ->addForeignKey('id_torrent_report_configuration', 'torrent_report_configuration', 'id_torrent_report_configuration')
            ->create();

        $this->query('
            insert into torrent_report_configuration (
                id_torrent_report_configuration, id_category, sequence, tracker_reason,
                resolve_warn, need_image, need_link, need_sitelink, need_track,
                is_active, is_admin, is_invisible, resolve_delete, resolve_upload,
                type, name, resolve_log, explanation, pm_body
            )
            select
                torrent_report_configuration_id, category_id, sequence, tracker_reason,
                resolve_warn, need_image, need_link, need_sitelink, need_track,
                case when is_active = 0 then false else true end,
                case when is_admin = 0 then false else true end,
                case when is_invisible = 0 then false else true end,
                case when resolve_delete = 0 then false else true end,
                case when resolve_upload = 0 then false else true end,
                type, name, resolve_log, explanation, pm_body
            from relay.torrent_report_configuration
        ');
        $this->query('
            insert into torrent_report_configuration_log (
                id_torrent_report_configuration_log, id_torrent_report_configuration,
                id_user, created, change_set
            )
            select
                torrent_report_configuration_log_id, torrent_report_configuration_id,
                user_id, created, change_set
            from relay.torrent_report_configuration_log
        ');
        // select s.seqrelid, c.relname from pg_sequence s inner join pg_class c on (c.oid = s.seqrelid)
        $this->query("
            select
                setval(
                    'torrent_report_configuration__id_torrent_report_configurati_seq',
                    (select max(id_torrent_report_configuration) from torrent_report_configuration)
                ),
                setval(
                    'torrent_report_configuration__id_torrent_report_configurati_seq',
                    (select max(id_torrent_report_configuration_log) from torrent_report_configuration_log)
                )
        ");
    }

    public function down(): void {
        $this->table('torrent_report_configuration_log')->drop()->save();
        $this->table('torrent_report_configuration')->drop()->save();
    }
}
