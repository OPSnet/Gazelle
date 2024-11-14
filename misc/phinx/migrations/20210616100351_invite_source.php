<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InviteSource extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void {
        $this->table('invite_source', ['id' => false, 'primary_key' => ['invite_source_id']])
            ->addColumn('invite_source_id', 'integer',  ['limit' => 10, 'signed' => false, 'identity' => true])
            ->addColumn('name',  'string',   ['limit' => 20, 'encoding' => 'ascii'])
            ->addIndex(['name'], ['unique' => true, 'name' => 'is_name_uidx'])
            ->create();

        $this->table('invite_source_pending', ['id' => false, 'primary_key' => ['invite_key']])
            ->addColumn('user_id',   'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('invite_source_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('invite_key', 'string',  ['limit' => 32])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('invite_source_id', 'invite_source', 'invite_source_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('user_has_invite_source', ['id' => false, 'primary_key' => ['user_id']])
            ->addColumn('user_id',   'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('invite_source_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('invite_source_id', 'invite_source', 'invite_source_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('inviter_has_invite_source', ['id' => false, 'primary_key' => ['user_id', 'invite_source_id']])
            ->addColumn('user_id',   'integer',  ['limit' => 10, 'signed' => false])
            ->addColumn('invite_source_id', 'integer',  ['limit' => 10, 'signed' => false])
            ->addForeignKey('user_id', 'users_main', 'ID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('invite_source_id', 'invite_source', 'invite_source_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
