<?php

namespace Gazelle\Task;

class LowerLoginAttempts extends \Gazelle\Task {
    public function run(): void {
        self::$db->prepared_query('
            UPDATE login_attempts
            SET Attempts = Attempts - 1
            WHERE Attempts > 0 AND LastAttempt < now() - INTERVAL 1 HOUR
        ');
        $this->processed = self::$db->affected_rows();

        self::$db->prepared_query('
            DELETE FROM login_attempts
            WHERE LastAttempt < (now() - INTERVAL 90 DAY)
        ');
        $this->processed += self::$db->affected_rows();
    }
}
