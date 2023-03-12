<?php

namespace Gazelle\Schedule\Tasks;

class ResolveStaffPms extends \Gazelle\Schedule\Task {
    public function run(): void {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations
            SET Status = 'Resolved', ResolverID = '0'
            WHERE Date < NOW() - INTERVAL 1 MONTH
                AND Status = 'Open'
                AND AssignedToUser IS NULL
            ");
    }
}
