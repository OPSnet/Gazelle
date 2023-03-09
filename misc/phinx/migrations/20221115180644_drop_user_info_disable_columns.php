<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUserInfoDisableColumns extends AbstractMigration {
    public function up(): void {
        $this->table('users_info')
            ->removeColumn('DisableAvatar')
            ->removeColumn('DisableForums')
            ->removeColumn('DisableIRC')
            ->removeColumn('DisableInvites')
            ->removeColumn('DisablePM')
            ->removeColumn('DisablePoints')
            ->removeColumn('DisablePosting')
            ->removeColumn('DisableRequests')
            ->removeColumn('DisableTagging')
            ->removeColumn('DisableUpload')
            ->removeColumn('DisableWiki')
            ->save();
    }

    public function down(): void {
        $this->table('users_info')
            ->addColumn('DisableAvatar',   'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableForums',   'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableIRC',      'enum', [ 'null' => true,  'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableInvites',  'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisablePM',       'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisablePoints',   'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisablePosting',  'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableRequests', 'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableTagging',  'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableUpload',   'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->addColumn('DisableWiki',     'enum', [ 'null' => false, 'default' => '0', 'values' => ['0', '1'], ])
            ->save();
    }
}
