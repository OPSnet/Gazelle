<?php

namespace Gazelle;

class StaffGroup extends BaseObject {
    final public const tableName = 'staff_groups';

    public function flush(): static {
        (new Manager\Privilege())->flush();
        self::$cache->delete_value(Manager\StaffGroup::LIST_KEY);
        unset($this->info);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s" class="tooltip" title="%s">%s</a>', $this->url(), 'Staff groups', 'Staff groups'); }
    public function location(): string { return 'tools.php?action=staff_groups'; }

    /**
     * A staff group object is so trival that it never needs to be instantiated,
     * apart from verifying that it exists to be removed. It normally appears
     * only in the context of a Manager\StaffGroup::groupList()
     */
    public function info(): array {
        return [];
    }

    public function remove(): int {
        $id = $this->id;
        self::$db->prepared_query("
            DELETE FROM staff_groups WHERE ID = ?
            ", $id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        self::$cache->delete_value(sprintf(Manager\StaffGroup::ID_KEY, $id));
        return $affected;
    }
}
