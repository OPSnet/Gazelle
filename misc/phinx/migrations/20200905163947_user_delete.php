<?php

use Phinx\Migration\AbstractMigration;

class UserDelete extends AbstractMigration {
    public function up(): void {
        $this->execute('ALTER TABLE user_bonus DROP FOREIGN KEY user_bonus_ibfk_1');
        $this->execute('ALTER TABLE user_bonus ADD FOREIGN KEY (user_id) REFERENCES users_main (ID) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->execute('ALTER TABLE user_flt DROP FOREIGN KEY user_flt_ibfk_1');
        $this->execute('ALTER TABLE user_flt ADD FOREIGN KEY (user_id) REFERENCES users_main (ID) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->execute('ALTER TABLE user_last_access DROP FOREIGN KEY user_last_access_ibfk_1');
        $this->execute('ALTER TABLE user_last_access ADD FOREIGN KEY (user_id) REFERENCES users_main (ID) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->execute('ALTER TABLE users_leech_stats DROP FOREIGN KEY users_leech_stats_ibfk_1');
        $this->execute('ALTER TABLE users_leech_stats ADD FOREIGN KEY (UserID) REFERENCES users_main (ID) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->execute('ALTER TABLE users_info ADD FOREIGN KEY (UserID) REFERENCES users_main (ID) ON UPDATE CASCADE ON DELETE CASCADE');
     }

     public function down(): void {
        $this->execute('ALTER TABLE user_bonus DROP FOREIGN KEY user_bonus_ibfk_1');
        $this->execute('ALTER TABLE user_bonus ADD FOREIGN KEY (user_id) REFERENCES users_main (ID)');
        $this->execute('ALTER TABLE user_flt DROP FOREIGN KEY user_flt_ibfk_1');
        $this->execute('ALTER TABLE user_flt ADD FOREIGN KEY (user_id) REFERENCES users_main (ID)');
        $this->execute('ALTER TABLE users_leech_stats DROP FOREIGN KEY users_leech_stats_ibfk_1');
        $this->execute('ALTER TABLE users_leech_stats ADD FOREIGN KEY (UserID) REFERENCES users_main (ID)');
        $this->execute('ALTER TABLE users_info DROP FOREIGN KEY users_info_ibfk_1');
     }
}
