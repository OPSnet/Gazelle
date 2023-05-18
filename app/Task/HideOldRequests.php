<?php

namespace Gazelle\Task;

class HideOldRequests extends \Gazelle\Task {
    public function run(): void {
        self::$db->prepared_query("
            UPDATE requests SET
                Visible = 0
            WHERE TimeFilled < (now() - INTERVAL 7 DAY)
        ");
        $this->processed = self::$db->affected_rows();
    }
}
