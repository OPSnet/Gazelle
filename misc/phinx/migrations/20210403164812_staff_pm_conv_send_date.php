<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StaffPmConvSendDate extends AbstractMigration
{
    public function up(): void {
        $this->table('staff_pm_messages')
            ->addIndex(['ConvID', 'SentDate'], [ 'name' => 'spm_conv_date_idx', ])
            ->removeIndex(['ConvID'])
            ->update();
    }

    public function down(): void {
        $this->table('staff_pm_messages')
            ->addIndex(['ConvID'])
            ->removeIndex(['ConvID', 'SentDate'])
            ->update();
    }
}
