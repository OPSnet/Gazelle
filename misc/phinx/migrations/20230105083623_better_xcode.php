<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BetterXcode extends AbstractMigration {
    public function up(): void {
        $this->table('better_transcode_music')
            ->drop()
            ->save();
        $this->table('better_transcode_music', ['id' => false, 'primary_key' => ['tgroup_id', 'edition'], 'encoding' => 'utf8mb4'])
            ->addColumn('tgroup_id', 'integer')
            ->addColumn('want_v0',  'boolean')
            ->addColumn('want_320', 'boolean')
            ->addColumn('edition', 'string', ['length' => 255]) // long enough to include concatenated Remaster* fields
            ->create();
    }

    public function down(): void {
        $this->table('better_transcode_music')
            ->drop()
            ->save();
        $this->table('better_transcode_music', ['id' => false, 'primary_key' => ['better_transcode_music_id'], 'encoding' => 'utf8mb4'])
            ->addColumn('better_transcode_music_id', 'integer', ['identity' => true])
            ->addColumn('tgroup_id', 'integer')
            ->addColumn('want_v0',  'boolean')
            ->addColumn('want_320', 'boolean')
            ->addColumn('edition', 'string', ['length' => 255])
            ->addIndex(['tgroup_id', 'edition'], ['name' => 'btm_tg_ed_idx'])
            ->create();
    }
}
