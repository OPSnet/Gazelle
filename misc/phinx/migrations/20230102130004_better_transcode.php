<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BetterTranscode extends AbstractMigration {
    public function up(): void {
        $this->table('better_transcode_music', ['id' => false, 'primary_key' => ['better_transcode_music_id'], 'encoding' => 'utf8mb4'])
            ->addColumn('better_transcode_music_id', 'integer', ['identity' => true])
            ->addColumn('tgroup_id', 'integer')
            ->addColumn('want_v0',  'boolean')
            ->addColumn('want_320', 'boolean')
            ->addColumn('edition', 'string', ['length' => 255]) // long enough to include concatenated Remaster* fields
            ->addIndex(['tgroup_id', 'edition'], ['name' => 'btm_tg_ed_idx'])
            ->create();

        $this->table('periodic_task')
             ->insert([
                 [
                     'name' => 'Better transcode',
                     'classname' => 'BetterTranscode',
                     'description' => 'Identify all music releases that lack a V0 or 320 transcode',
                     'period' => 7200,
                 ],
             ])
             ->save();
    }

    public function down(): void {
        $this->getQueryBuilder()
            ->delete('periodic_task')
            ->where(function ($exp) {
                return $exp->in('classname', ['BetterTranscode']);
            })
            ->execute();
        $this->table('better_transcode_music')
            ->drop()
            ->save();
    }
}
