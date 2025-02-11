<?php

namespace Gazelle\Manager;

class DNU extends \Gazelle\Base {
    use \Gazelle\Pg;

    public function create(
        string        $name,
        string        $description,
        \Gazelle\User $user,
    ): int {
        return $this->pg()->insert("
            insert into do_not_upload
                   (name, description, id_user, sequence)
            VALUES (?,    ?,           ?,       9999)
            ", $name, $description, $user->id()
        );
    }

    public function modify(
        int           $id,
        string        $name,
        string        $description,
        \Gazelle\User $user,
    ): int {
        return $this->pg()->prepared_query("
            update do_not_upload set
                name        = ?,
                description = ?,
                id_user     = ?
            where id_do_not_upload = ?
            returning id_do_not_upload
            ", $name, $description, $user->id(), $id
        );
    }

    public function remove(int $id): int {
        return $this->pg()->prepared_query("
            delete from do_not_upload where id_do_not_upload = ?
            ", $id
        );
    }

    public function reorder(array $list): int {
        $sequence = 0;
        $case = [];
        $args = [];
        foreach ($list as $id) {
            $case[] = "when id_do_not_upload = ? then ?::int";
            array_push($args, $id, ++$sequence);
        }
        $sql = "update do_not_upload set sequence = case "
            . implode(' ', $case)
            . ' end';
        return $this->pg()->prepared_query($sql, ...$args);
    }

    public function dnuList(): array {
        return $this->pg()->all("
            SELECT d.id_do_not_upload,
                d.name,
                d.description,
                d.id_user,
                d.created,
                case when d.created > now() - '1 month'::interval
                    then 1 else 0 end as is_new
            from do_not_upload d
            order by d.sequence
        ");
    }

    public function latest(): string {
        return (string)$this->pg()->scalar("
            select greatest('-infinity', max(created)) from do_not_upload
        ");
    }

    public function hasNewForUser(\Gazelle\User $user): bool {
        return (bool)$this->pg()->scalar("
            select coalesce(max(t.created), '-infinity') < ?
            from relay.torrents t
            where t.\"UserID\" = ?
            ", $this->latest(), $user->id()
        );
    }
}
