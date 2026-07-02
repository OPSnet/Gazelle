<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class Riplog extends AbstractMigration {
    public function up(): void {
        $this->query("create type riplog_checksum_t as enum ('ok', 'invalid', 'missing')");

        $this->table('riplog', ['id' => false, 'primary_key' => 'id_riplog'])
            ->addColumn('id_riplog', 'integer', ['identity' => true])
            ->addColumn('id_torrent', 'integer')
            ->addColumn('checksum', Literal::from('riplog_checksum_t'))
            ->addColumn('score', 'smallinteger')
            ->addColumn('riplog_language', 'char', ['length' => 2])
            ->addColumn('ripper', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('logchecker_version', 'string', ['limit' => 16])
            ->addColumn('ripper_version', 'string', ['limit' => 48, 'null' => true])
            ->addColumn('filename', 'string', ['limit' => 255])
            ->addColumn('details', 'text[]')
            ->save();

        $this->table('riplog_adjust', ['id' => false, 'primary_key' => 'id_riplog'])
            ->addColumn('id_riplog', 'integer')
            ->addColumn('checksum', Literal::from('riplog_checksum_t'))
            ->addColumn('score', 'smallinteger')
            ->addColumn('id_user', 'integer')
            ->addColumn('reason', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('details', 'text[]')
            ->addForeignKey('id_riplog', 'riplog', 'id_riplog',
                ['delete' => 'CASCADE', 'update' => 'CASCADE']
            )
            ->save();
    }

    public function down(): void {
        $this->table('riplog_adjust')->drop()->save();
        $this->table('riplog')->drop()->save();
        $this->query('drop type riplog_checksum_t');
    }
}
