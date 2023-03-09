<?php

use Phinx\Migration\AbstractMigration;

class ForumEditPermissions extends AbstractMigration {
    public function up(): void {
        $table = $this->table('forums_transitions', ['id' => false, 'primary_key' => 'forums_transitions_id']);

        $table
            ->addColumn('forums_transitions_id', 'integer', ['limit' => 10, 'identity' => true])
            ->addColumn('source', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('destination', 'integer', ['limit' => 6, 'signed' => false])
            ->addColumn('label', 'string', ['limit' => 20])
            ->addColumn('permission_levels', 'string', ['limit' => 50])
            ->addForeignKey('source', 'forums', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('destination', 'forums', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE']);

        if (defined('TRASH_FORUM_ID')) {
            $builder = $this->getQueryBuilder();
            $statement = $builder
                ->select('f.ID')
                ->from(['f' => 'forums'])
                ->join(['fc' => [
                    'table' => 'forums_categories',
                    'conditions' => 'f.CategoryID = fc.ID'
                ]])
                ->orderAsc('fc.Sort')
                ->orderAsc('f.Sort')
                ->execute();
            $insertData = [];
            foreach((array)$statement->fetchAll('assoc') as $row) {
                if ($row['ID'] === TRASH_FORUM_ID) {
                    continue;
                }
                $insertData[] = [
                    'source' => (int) $row['ID'],
                    'destination' => TRASH_FORUM_ID,
                    'label' => 'Trash',
                    'permission_levels' => ''
                ];
            }
            $table->insert($insertData);
        }
        $table->create();
    }

    public function down(): void {
        $this->table('forums_transitions')->drop()->update();
    }
}
