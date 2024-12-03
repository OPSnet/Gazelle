<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class WikiEditReadable extends AbstractMigration {
    public function up(): void {
        $this->table('user_attr')
            ->insert([
                [
                    'Name'        => 'wiki-edit-readable',
                    'Description' => 'Can edit any wiki article they can read',
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->query("
            delete from user_attr where Name = 'wiki-edit-readable'
        ");
    }
}
