<?php

namespace Gazelle;

class Privilege extends BaseObject {
    protected array $info;

    public function tableName(): string {
        return 'permissions';
    }

    public function flush() {
        $this->info = [];
    }

    public function info(): array {
        if (empty($this->info)) {
            $this->info = $this->db->rowAssoc("
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

    public function staffGroup(): ?int {
        return $this->info()['StaffGroup'];
    }

    public function userTotal(): int {
        return $this->info()['total'];
    }

    public function values(): array {
        return $this->info()['Values'];
    }

    protected function userFlush(array $ids): int {
        $this->cache->deleteMulti(array_merge(
            ["perm_" . $this->id],
            array_map(fn($id) => "user_info_heavy_$id", $ids),
            array_map(fn($id) => "u_$id", $ids),
        ));
        return count($ids);
    }

    public function modify(): bool {
        $modified = parent::modify();
        if ($modified) {
            $this->db->prepared_query(
                $this->isSecondary()
                ? "SELECT DISTINCT UserID FROM users_levels WHERE PermissionID = ?"
                : "SELECT ID FROM users_main WHERE PermissionID = ?"
                , $this->id
            );
            $this->userFlush($this->db->collect(0, false));
        }
        return $modified;
    }

    public function remove(): int {
        if ($this->isSecondary()) {
            $this->db->prepared_query("
                SELECT DISTINCT UserID FROM users_levels WHERE PermissionID = ?
                ", $this->id
            );
            $this->userFlush($this->db->collect(0, false));
            $this->db->prepared_query("
                DELETE FROM users_levels WHERE PermissionId = ?
                ", $this->id
            );
        }

        $this->db->prepared_query("
            DELETE FROM permissions WHERE ID = ?
            ", $this->id
        );
        $this->cache->delete_value('classes');
        return $this->db->affected_rows();
    }
}
