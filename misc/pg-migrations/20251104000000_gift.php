<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class Gift extends AbstractMigration {
    public function up(): void {
        $this->table('gift_event', ['id' => false, 'primary_key' => 'id_gift_event'])
            ->addColumn('id_gift_event', 'integer', ['identity' => true])
            ->addColumn('id_user', 'integer')
            ->addColumn('open', Literal::from('tstzrange'))
            ->addColumn('name', 'string')
            ->save();
        $this->query("
            alter table gift_event
                add constraint ge_open_excl exclude using gist (open with &&)
            ");

        $this->table('gift', ['id' => false, 'primary_key' => 'id_gift'])
            ->addColumn('id_gift', 'integer', ['identity' => true])
            ->addColumn('weight', 'integer')
            ->addColumn('factor', 'integer')
            ->addColumn('duration', 'integer', ['comment' => 'hours'])
            ->addColumn('label', 'string', ['length' => 20])
            ->addColumn('description', 'text')
            ->addIndex('label', ['unique' => true])
             ->insert([
                [
                    'description'   => 'Add 1 GiB to your upload stats',
                    'duration'      => 0,
                    'label'         => 'up-1',
                    'factor'        => 1,
                    'weight'        => 75000,
                ],
                [
                    'description'   => 'Add 5 GiB to your upload stats',
                    'duration'      => 0,
                    'label'         => 'up-5',
                    'factor'        => 5,
                    'weight'        => 32000,
                ],
                [
                    'description'   => 'Add 10 GiB to your upload stats',
                    'duration'      => 0,
                    'label'         => 'up-10',
                    'factor'        => 10,
                    'weight'        => 8200,
                ],
                [
                    'description'   => 'Add 100 GiB to your upload stats',
                    'duration'      => 0,
                    'label'         => 'up-100',
                    'factor'        => 100,
                    'weight'        => 900,
                ],
                [
                    'description'   => 'Add 200 GiB to your upload stats',
                    'duration'      => 0,
                    'label'         => 'up-200',
                    'factor'        => 200,
                    'weight'        => 750,
                ],
                [
                    'description'   => 'Add 100 GiB to one of your requests',
                    'duration'      => 0,
                    'label'         => 'reqc-100',
                    'factor'        => 100,
                    'weight'        => 4500,
                ],
                [
                    'description'   => 'Add 100 GiB to one of your voted requests',
                    'duration'      => 0,
                    'label'         => 'reqv-100',
                    'factor'        => 100,
                    'weight'        => 5500,
                ],
                [
                    'description'   => 'Remove 1 GiB from your download stats',
                    'duration'      => 0,
                    'label'         => 'down-1',
                    'factor'        => 1,
                    'weight'        => 65000,
                ],
                [
                    'description'   => 'Remove 5 GiB from your download stats',
                    'duration'      => 0,
                    'label'         => 'down-5',
                    'factor'        => 5,
                    'weight'        => 22000,
                ],
                [
                    'description'   => 'Remove 10 GiB from your download stats',
                    'duration'      => 0,
                    'label'         => 'down-10',
                    'factor'        => 10,
                    'weight'        => 12000,
                ],
                [
                    'description'   => 'Remove 50 GiB from your download stats',
                    'duration'      => 0,
                    'label'         => 'down-50',
                    'factor'        => 50,
                    'weight'        => 6100,
                ],
                [
                    'description'   => '10,000 Bonus points',
                    'duration'      => 0,
                    'label'         => 'bonus-10000',
                    'factor'        => 10000,
                    'weight'        => 23000,
                ],
                [
                    'description'   => '50,000 Bonus points',
                    'duration'      => 0,
                    'label'         => 'bonus-50000',
                    'factor'        => 50000,
                    'weight'        => 10500,
                ],
                [
                    'description'   => '100,000 Bonus points',
                    'duration'      => 0,
                    'label'         => 'bonus-100000',
                    'factor'        => 100000,
                    'weight'        => 4900,
                ],
                [
                    'description'   => '100 Freeleech tokens',
                    'duration'      => 0,
                    'label'         => 'token-100',
                    'factor'        => 100,
                    'weight'        => 26500,
                ],
                [
                    'description'   => '200 Freeleech tokens',
                    'duration'      => 0,
                    'label'         => 'token-200',
                    'factor'        => 200,
                    'weight'        => 15500,
                ],
                [
                    'description'   => '500 Freeleech tokens',
                    'duration'      => 0,
                    'label'         => 'token-500',
                    'factor'        => 500,
                    'weight'        => 7800,
                ],
                [
                    'description'   => 'Personal collage',
                    'duration'      => 0,
                    'label'         => 'collage',
                    'factor'        => 1,
                    'weight'        => 2000,
                ],
                [
                    'description'   => 'Lump of coal',
                    'duration'      => 0,
                    'label'         => 'coal',
                    'factor'        => 1,
                    'weight'        => 500,
                ],
            ])
            ->save();
        $this->table('user_has_gift', ['id' => false, 'primary_key' => 'id_user_has_gift'])
            ->addColumn('id_user_has_gift', 'integer', ['identity' => true])
            ->addColumn('id_user', 'integer')
            ->addColumn('id_gift', 'integer')
            ->addColumn('created', 'timestamp', ['timezone' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['id_gift'], ['name' => 'uhg_g_idx'])
            ->addIndex(['id_user'], ['name' => 'uhg_u_idx'])
            ->addForeignKey('id_gift', 'gift', 'id_gift', ['delete' => 'CASCADE'])
            ->save();
        $this->table('user_has_gift_validity')
            ->addColumn('id_user_has_gift', 'integer')
            ->addColumn('validity', Literal::from('tstzrange'))
            ->addForeignKey('id_user_has_gift', 'user_has_gift', 'id_user_has_gift', ['delete' => 'CASCADE'])
            ->save();
        $this->table('gift_event_user_has_gift', ['id' => false, 'primary_key' => ['id_gift_event', 'id_user_has_gift']])
            ->addColumn('id_gift_event', 'integer')
            ->addColumn('id_user_has_gift', 'integer')
            ->addForeignKey('id_gift_event', 'gift_event', 'id_gift_event', ['delete' => 'CASCADE'])
            ->addForeignKey('id_user_has_gift', 'user_has_gift', 'id_user_has_gift')
            ->save();
    }

    public function down(): void {
        $this->table('gift_event_user_has_gift')->drop()->save();
        $this->table('user_has_gift_validity')->drop()->save();
        $this->table('user_has_gift')->drop()->save();
        $this->table('gift')->drop()->save();
        $this->table('gift_event')->drop()->save();
    }
}
