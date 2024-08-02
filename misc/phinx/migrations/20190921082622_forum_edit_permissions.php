<?php

use Phinx\Migration\AbstractMigration;

class ForumEditPermissions extends AbstractMigration {
    public function up(): void {
        $this->table('forums_transitions', ['id' => false, 'primary_key' => 'forums_transitions_id'])
            ->addColumn('forums_transitions_id', 'integer', ['identity' => true])
            ->addColumn('source',                'integer')
            ->addColumn('destination',           'integer')
            ->addColumn('label',                 'string', ['limit' => 20])
            ->addColumn('permission_levels',     'string', ['limit' => 50])
            ->addForeignKey('source',      'forums', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('destination', 'forums', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        if (defined('TRASH_FORUM_ID')) {
            $this->execute("
                INSERT INTO forums_transitions (source, destination, label, permission_levels)
                SELECT f.ID, " . TRASH_FORUM_ID . ", 'Trash', ''
                FROM forums f
                WHERE f.ID != " . TRASH_FORUM_ID
            );
        }
    }

    public function down(): void {
        $this->table('forums_transitions')->drop()->save();
    }
}
