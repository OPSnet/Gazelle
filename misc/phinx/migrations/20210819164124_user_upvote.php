<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class UserUpvote extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users_votes')
            ->addColumn('upvote', 'integer', ['default' => 1, 'limit' => MysqlAdapter::INT_TINY])
            ->addIndex(['upvote','GroupID','UserID'], ['name' => 'uv_t_grp_usr_idx'])
            ->addIndex(['UserID', 'Time'], [ 'name' => 'uv_u_t_idx'])
            ->update();

        $this->execute("UPDATE users_votes SET upvote = if(Type = 'Up', 1, 0)");
    }

    public function down(): void
    {
        $this->table('users_votes')
            ->removeColumn('upvote')
            ->removeIndex(['upvote','GroupID','UserID'])
            ->removeIndex(['UserID', 'Time'])
            ->update();
    }
}
