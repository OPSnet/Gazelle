<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/************************************************************

To initialize a running database, execute the following query
once the code has been deployed.

BEGIN;

DELETE FROM user_has_ordinal
WHERE user_ordinal_id =
    (SELECT user_ordinal_id FROM user_ordinal WHERE name = 'personal-collage');

INSERT INTO user_has_ordinal (user_ordinal_id, user_id, value)
SELECT (SELECT user_ordinal_id FROM user_ordinal WHERE name = 'personal-collage'),
    ID,
    collage_total
FROM users_main
WHERE collage_total > 0;

COMMIT;

************************************************************/

final class UserOrdinal extends AbstractMigration {
    public function up(): void {
        $this->table('user_ordinal', ['id' => false, 'primary_key' => 'user_ordinal_id'])
            ->addColumn('user_ordinal_id', 'integer', ['identity' => true])
            ->addColumn('default_value',   'biginteger')
            ->addColumn('name',        'string', ['limit' => 32])
            ->addColumn('description', 'string', ['limit' => 500])
            ->addIndex(['name'], ['unique' => true])
            ->save();

        $this->table('user_has_ordinal', ['id' => false, 'primary_key' => ['user_ordinal_id', 'user_id']])
            ->addColumn('user_ordinal_id', 'integer')
            ->addColumn('user_id',         'integer')
            ->addColumn('value',           'biginteger')
            ->addIndex(['user_id'], ['name' => 'uho_u_idx'])
            ->addForeignKey('user_ordinal_id', 'user_ordinal', 'user_ordinal_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id',         'users_main',   'ID',              ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('user_ordinal')
             ->insert([
                [
                    'name'          => 'personal-collage',
                    'description'   => 'The number of personal collages the user has been granted',
                    'default_value' => 0,
                ],
                [
                    'name'          => 'request-bounty-create',
                    'description'   => 'The default bounty used when creating a request',
                    'default_value' => 100 * 1024 * 1024, // 100MiB
                ],
                [
                    'name'          => 'request-bounty-vote',
                    'description'   => 'The default bounty used when voting on a request',
                    'default_value' => 100 * 1024 * 1024, // 100MiB
                ],
             ])
             ->save();
    }

    public function down(): void {
        $this->table('user_has_ordinal')->drop()->save();
        $this->table('user_ordinal')->drop()->save();
    }
}
