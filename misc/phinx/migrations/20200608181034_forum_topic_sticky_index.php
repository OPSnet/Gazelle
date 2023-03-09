<?php

use Phinx\Migration\AbstractMigration;

class ForumTopicStickyIndex extends AbstractMigration {
    public function change(): void {
        $this->table('forums_topics')
            ->addIndex(['ForumID', 'IsSticky'], ['name' => 'ft_fid_sticky_idx'])
            ->save();
    }
}
