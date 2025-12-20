<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RequestFkey extends AbstractMigration {
    public function up(): void {
        foreach (['request_artist', 'request_tag'] as $t) {
            $this->table($t)
                ->addForeignKey('id_request', 'request', 'id_request', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->save();
        }
    }

    public function down(): void {
        foreach (['request_artist', 'request_tag'] as $t) {
            $this->table($t)
                ->dropForeignKey('id_request')
                ->save();
        }
    }
}
