<?php

use Phinx\Seed\AbstractSeed;

class UserAttrHideVote extends AbstractSeed
{
    public function run(): void {
        foreach (
            [
            ['hide-vote-recent', 'Do not show recent votes on profile page'],
            ['hide-vote-history', 'Do not show link to vote history on profile page'],
            ] as $row
        ) {
            $this->table('user_attr')->insert([
                'Name' => $row[0],
                'Description' => $row[1],
            ])->save();
        }
        $this->query("
            INSERT INTO user_has_attr (UserID, UserAttrID)
            SELECT DISTINCT uv.UserID, (SELECT ID FROM user_attr WHERE Name = 'hide-vote-history')
            FROM users_votes uv
            INNER JOIN users_main um ON (um.ID = uv.UserID)
            WHERE um.Enabled = '1'
        ");
    }
}
