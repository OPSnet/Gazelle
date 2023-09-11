<?php

namespace Gazelle;

class Privilege extends BaseObject {
    final const tableName = 'permissions';

    public function flush(): Privilege { $this->info = []; return $this; }
    public function location(): string { return ''; }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), $this->url()); }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $info = self::$db->rowAssoc("
            SELECT p.Name,
                p.Level,
                p.Secondary,
                p.PermittedForums,
                p.Values,
                p.DisplayStaff,
                p.StaffGroup,
                p.badge,
                count(u.ID) + count(DISTINCT l.UserID) AS total
            FROM permissions AS p
            LEFT JOIN users_main AS u ON (u.PermissionID = p.ID)
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID)
            WHERE p.ID = ?
            GROUP BY p.ID
            ", $this->id
        );
        $info['Values'] = unserialize($info['Values']);
        $this->info = $info;
        return $this->info;
    }

    public function badge(): ?string {
        return $this->info()['badge'];
    }

    public function displayStaff(): bool {
        return $this->info()['DisplayStaff'] == '1';
    }

    public function level(): int {
        return $this->info()['Level'];
    }

    public function name(): string {
        return $this->info()['Name'];
    }

    public function isSecondary(): bool {
        return $this->info()['Secondary'] == 1;
    }

    public function permittedForums(): array {
        $list = $this->info()['PermittedForums'];
        return !empty($list) ? explode(',', $list) : [];
    }

    public function staffGroupId(): ?int {
        return $this->info()['StaffGroup'];
    }

    public function userTotal(): int {
        return $this->info()['total'];
    }

    public function values(): array {
        return $this->info()['Values'];
    }

    protected function userFlush(array $ids): int {
        self::$cache->delete_multi(array_merge(
            ["perm_" . $this->id],
            array_map(fn($id) => "u_$id", $ids),
        ));
        return count($ids);
    }

    public function modify(): bool {
        $modified = parent::modify();
        if ($modified) {
            self::$db->prepared_query(
                $this->isSecondary()
                ? "SELECT DISTINCT UserID FROM users_levels WHERE PermissionID = ?"
                : "SELECT ID FROM users_main WHERE PermissionID = ?"
                , $this->id
            );
            $this->userFlush(self::$db->collect(0, false));
        }
        self::$cache->delete_multi(['user_class', 'staff_class']);
        return $modified;
    }

    public function remove(): int {
        if ($this->isSecondary()) {
            self::$db->prepared_query("
                SELECT DISTINCT UserID FROM users_levels WHERE PermissionID = ?
                ", $this->id
            );
            $this->userFlush(self::$db->collect(0, false));
            self::$db->prepared_query("
                DELETE FROM users_levels WHERE PermissionId = ?
                ", $this->id
            );
        }

        self::$db->prepared_query("
            DELETE FROM permissions WHERE ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value('classes');
        return $affected;
    }
}
