<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require_once __DIR__ . '/../../lib/config.php';
// phpcs:enable PSR1.Files.SideEffects.FoundWithSymbols

use Phinx\Migration\AbstractMigration;

final class BonusPointAccrualStats extends AbstractMigration {
    public function up(): void {
        $this->query("
            drop foreign table if exists relay.user_summary
        ");
        $this->query("
            import foreign schema " . MYSQL_DB
                . " limit to (user_summary) from server relayer into relay;
        ");
        $this->query('
            create or replace function category_bonus_accrual(
                size bigint, 
                seedtime double precision, 
                seeders integer, 
                scale double precision
            ) returns double precision as $$
            begin
                return (size / scale)
                    / power(1024, 3)
                    * (0.0433 + (0.07 * ln(1 + seedtime/24)) / power(greatest(seeders, 1), 0.35));
            end;
            $$ language plpgsql immutable
        ');
    }

    public function down(): void {
        $this->query("
            drop foreign table if exists relay.user_summary
        ");
        $this->query("
            import foreign schema " . MYSQL_DB
                . " limit to (user_summary) from server relayer into relay;
        ");
        $this->query('
            drop function category_bonus_accrual;
        ');
    }
}
