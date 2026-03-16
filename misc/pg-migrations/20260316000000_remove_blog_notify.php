<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveBlogNotify extends AbstractMigration {
    public function up(): void {
        $this->table('blog')->removeColumn('notify')->save();
    }

    public function down(): void {
        $this->table('blog')->addColumn('notify', 'boolean')->save();
    }
}
