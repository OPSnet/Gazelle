<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BetterTgroupScore extends AbstractMigration {
    public function up(): void {
        $this->query('
            create or replace function binomial_ci(p int, n int)
                returns float
                immutable
                language plpgsql
            as $$
                declare
                    result float;
                    Z_VAL float;
                begin
                    if p <= 0 or n <= 0 then
                        result := 0.0;
                    else
                        Z_VAL  := 1.281728756502709;
                        result := (
                            (p / n)
                                + Z_VAL * Z_VAL / (2 * n)
                                - Z_VAL * sqrt(((p / n) * (1 - (p / n)) + Z_VAL * Z_VAL / (4 * n)) / n)
                            )
                            / (1 + Z_VAL * Z_VAL / n);
                    end if;
                    return result;
                end;
            $$;
        ');
    }

    public function down(): void {
        $this->query('
            create or replace function binomial_ci(p int, n int)
                returns float
                immutable
                language plpgsql
            as $$
                declare
                    result float;
                begin
                    if n = 0 then
                        result := 0.0;
                    else
                        result := ((p + 1.35336) / n - 1.6452 * sqrt((p * (n - p))::float / n + 0.67668) / n) / (1 + 2.7067 / n);
                    end if;
                    return result;
                end;
            $$;
        ');
    }
}
