<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDisableUserAttrs extends AbstractMigration
{
    public function up(): void
    {
         $rows = [
            ['Name' => 'disable-avatar', 'Description' => 'This user has avatar privileges disabled'],
            ['Name' => 'disable-forums', 'Description' => 'This user has forum privileges disabled'],
            ['Name' => 'disable-irc', 'Description' => 'This user has IRC privileges disabled'],
            ['Name' => 'disable-pm', 'Description' => 'This user has PM privileges disabled'],
            ['Name' => 'disable-bonus-points', 'Description' => 'This user has bonus point privileges disabled'],
            ['Name' => 'disable-posting', 'Description' => 'This user has posting privileges disabled'],
            ['Name' => 'disable-requests', 'Description' => 'This user has request privileges disabled'],
            ['Name' => 'disable-tagging', 'Description' => 'This user has tagging privileges disabled'],
            ['Name' => 'disable-upload', 'Description' => 'This user has upload privileges disabled'],
            ['Name' => 'disable-wiki', 'Description' => 'This user has wiki privileges disabled'],
            ['Name' => 'disable-invites', 'Description' => 'This user has invite privileges disabled'],
            ['Name' => 'disable-leech', 'Description' => 'This user has leeching privileges disabled']
         ];
         $this->table('user_attr')->insert($rows)->save();
    }

    public function down(): void
    {
         $this->execute("DELETE FROM user_attr where Name = 'disable-avatar'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-forums'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-invites'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-irc'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-pm'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-bonus'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-posting'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-requests'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-tagging'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-upload'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-wiki'");
         $this->execute("DELETE FROM user_attr where Name = 'disable-leech'");
    }
}
