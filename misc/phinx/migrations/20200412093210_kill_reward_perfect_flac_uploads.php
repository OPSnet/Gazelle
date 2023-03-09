<?php

use Phinx\Migration\AbstractMigration;

class KillRewardPerfectFlacUploads extends AbstractMigration {
    public function up(): void {
        $this->execute("
            DELETE FROM periodic_task
            WHERE classname = 'RewardPerfectFlacUploads'
        ");
    }

    public function down(): void {
        $this->table('periodic_task')
             ->insert([[
                'name' => 'Perfect FLAC Token Handout',
                'classname' => 'RewardPerfectFlacUploads',
                'description' => 'Hands out tokens and invites for perfect FLACs',
                'period' => 60 * 60,
                'is_enabled' => false
             ]])
             ->save();
    }
}
