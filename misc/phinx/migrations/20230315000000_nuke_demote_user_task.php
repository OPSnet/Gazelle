<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NukeDemoteUserTask extends AbstractMigration {
    public function up(): void {
        $this->execute("
            DELETE FROM periodic_task WHERE classname IN ('DemoteUsers', 'FrontPageStats')
        ");
    }

    public function down(): void {
        $this->table('periodic_task')
            ->insert([
                [
                    'name'        => 'Demote Users',
                    'classname'   => 'DemoteUsers',
                    'description' => 'Demotes users',
                    'period'      => 60 * 60
                ],[
                    'name'        => 'Front Page Stats',
                    'classname'   => 'FrontPageStats',
                    'description' => 'Updates the stats for the front page',
                    'period'      => 60 * 60
                ]
            ])
            ->save();
    }
}
