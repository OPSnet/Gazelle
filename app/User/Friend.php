<?php

namespace Gazelle\User;

class Friend extends \Gazelle\BasePgUser {
    final public const tableName = 'user_has_friend';

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    public function isFriend(\Gazelle\User $friend): bool {
        return (bool)$this->pg()->scalarex('
            select 1
            from user_has_friend
            where id_user = $1
                and id_user_friend = $2
            ', $this->user->id, $friend->id
        );
    }

    public function isMutual(\Gazelle\User $friend): bool {
        return (bool)$this->pg()->scalarex('
            select 1
            from user_has_friend a
            inner join user_has_friend b ON (b.id_user = a.id_user_friend AND b.id_user_friend = $1)
            where a.id_user = $1
                and a.id_user_friend = $2
            ', $this->user->id, $friend->id
        );
    }

    public function add(\Gazelle\User $friend): int {
        if ($this->user->id === $friend->id) {
            return -1;
        }
        return $this->pg()->query("
            insert into user_has_friend
                   (id_user, id_user_friend)
            values ($1,      $2)
            on conflict (id_user, id_user_friend)
                do nothing
            ", $this->user->id, $friend->id
        );
    }

    public function setComment(\Gazelle\User $friend, string $comment): int {
        return $this->pg()->query('
            update user_has_friend set
                comment = $1
            where id_user = $2
                and id_user_friend = $3
            ', $comment, $this->user->id, $friend->id
        );
    }

    public function removeFriend(\Gazelle\User $friend): int {
        return $this->pg()->query('
            delete from user_has_friend
            where id_user = $1
                and id_user_friend = $2
            ', $this->user->id, $friend->id
        );
    }

    public function total(): int {
        return (int)$this->pg()->scalarex('
            select count(*) from user_has_friend where id_user = $1
            ', $this->user->id
        );
    }

    public function page(
        int $limit,
        int $offset,
        \Gazelle\Manager\User $manager = new \Gazelle\Manager\User(),
    ): array {
        $result = $this->pg()->allex(<<<END_SQL
            select a.id_user_friend,
                a.comment,
                case when b.id_user is null then 0 else 1 end as mutual
            from user_has_friend        a
            inner join relay.users_main um on (um."ID" = a.id_user_friend)
            left join user_has_friend   b  on (b.id_user = a.id_user_friend AND b.id_user_friend = $1)
            WHERE a.id_user = $1
            ORDER BY um."Username"
            LIMIT $2 OFFSET $3
            END_SQL,
                $this->user->id, $limit, $offset
        );
        $list = [];
        foreach ($result as $row) {
            $row['user'] = $manager->findById($row['id_user_friend']);
            $list[$row['id_user_friend']] = $row;
        }
        return $list;
    }

    public function userList(
        \Gazelle\Manager\User $manager = new \Gazelle\Manager\User(),
    ): array {
        return array_map(
            fn ($id) => $manager->findById($id),
            $this->pg()->columnex(<<<END_SQL
                select f.id_user_friend
                from user_has_friend        f
                inner join relay.users_main u on (u."ID" = f.id_user_friend)
                where f.id_user = $1
                order by u."Username"
                END_SQL,
                    $this->user->id
            ),
        );
    }
}
