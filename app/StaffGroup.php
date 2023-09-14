<?php

namespace Gazelle;

class StaffGroup extends BaseObject {
    final const tableName = 'staff_groups';

    public function flush(): static {
        self::$cache->delete_value('staff');
        (new Manager\Privilege)->flush();
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s" class="tooltip" title="%s">%s</a>', $this->url(), 'Staff groups', 'Staff groups'); }
    public function location(): string { return 'tools.php?action=staff_groups'; }

    public function info(): array {
        return [];
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM staff_groups WHERE ID = ?
            ", $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }
}
