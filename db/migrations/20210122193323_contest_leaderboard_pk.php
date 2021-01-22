<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ContestLeaderboardPk extends AbstractMigration
{
    public function up(): void {
        $this->execute("
            ALTER TABLE contest_leaderboard ADD PRIMARY KEY (contest_id, user_id)
        ");
    }

    public function down(): void {
        $this->execute("
            ALTER TABLE contest_leaderboard DROP PRIMARY KEY
        ");
    }
}
