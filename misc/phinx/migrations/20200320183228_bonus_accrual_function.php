<?php

use Phinx\Migration\AbstractMigration;

class BonusAccrualFunction extends AbstractMigration {
    public function up(): void {
        $this->execute('
            CREATE FUNCTION IF NOT EXISTS bonus_accrual(Size bigint, Seedtime float, Seeders integer)
            RETURNS float DETERMINISTIC NO SQL
            RETURN Size / pow(1024, 3) * (0.0433 + (0.07 * ln(1 + Seedtime/24)) / pow(greatest(Seeders, 1), 0.35))
        ');
    }

    public function down(): void {
        $this->execute('
            DROP FUNCTION bonus_accrual
        ');
    }
}
