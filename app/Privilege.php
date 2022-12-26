<?php

namespace Gazelle;

class Privilege extends BaseObject {
    protected array $info;

    public function tableName(): string {
        return 'permissions';
    }

    public function location(): string {
        return '';
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), $this->url());
    }

    public function flush() {
        $this->info = [];
    }

    public function info(): array {
        if (empty($this->info)) {
            $this->info = self::$db->rowAssoc("
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
            $this->info['Values'] = unserialize($this->info['Values']);
        }
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
        return explode(',', $this->info()['PermittedForums']) ?? [];
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
        self::$cache->deleteMulti(array_merge(
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
        self::$cache->deleteMulti(['user_class', 'staff_class']);
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
        self::$cache->delete_value('classes');
        return self::$db->affected_rows();
    }
}
