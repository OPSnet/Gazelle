<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserWarning2 extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE user_warning add reason text check (char_length(reason) <= 32000);');
        $this->execute('UPDATE user_warning set reason = \'\';');
        $this->execute('ALTER TABLE user_warning alter column reason set not null;');
    }

    public function down()
    {
    }
}
